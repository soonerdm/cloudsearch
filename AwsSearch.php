<?php

namespace App\Console\Commands;

use App\Product;
use App\ShoppingProduct;
use App\Store;
use Aws\CloudSearch\CloudSearchClient;
use Aws\CloudSearchDomain\CloudSearchDomainClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use phpDocumentor\Reflection\Types\Object_;

class AwsSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:search';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return Object
     */
    private function stores(){
        return Store::whereIn('store_code', ['2701', '1230', '9515','1006','3501'])->where('aws_search_file', false)->first();
    }

    private function mark_store_processed($id){
        $s = Store::find($id);
        $s->aws_search_file  = 1;
        $s->save();
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $store = $this->stores();

        if(isset($store)) {

            // We mark this updated here so that if it times out
            // it will move to the next store
            $this->mark_store_processed($store->id);
            $products = $this->search_documents($store->store_code);
            $this->put_aws($products, $store->store_code);
        }

    }

    public function search_documents($store){

        $results = DB::select(DB::raw('SELECT p.id, p.name, p.upc, p.image, p.description as description, p.brand_name as brand_name, p.size, p.kwikee_name, p.kwikee_description,
                            p.kwikee_brand_name,
                            p.kwikee_size, p.keywords, s.description as shopping_desc, s.store_number, s.ad_flag, s.regular_price, s.sale_price, s.sale_qty, s.scale_flag
                            , s.promo_tag, s.tax_pct, s.dept_code
                            ,s.promo_start_date, s.promo_end_date, s.movement, c.name as category_name , c.id as category_id
                            FROM products as p join shopping_products as s ON p.upc = s.sku
                            JOIN category_product cp ON cp.product_id = p.id
                            JOIN categories c ON c.id = cp.category_id
                            WHERE store_number = '.$store ));

            return $results;


//        return Product::join('shopping_products', 'products.upc', '=', 'shopping_products.sku')
//            ->join('category_product',  'category_product.product_id' ,  '=', 'products.id')
//            ->join('categories', 'categories.id', '=' , 'category_product.category_id')
//            ->where('shopping_products.store_number', $store)
//            ->select(DB::raw('*,products.brand_name as brand_name,products.description as description'))->get();


    }


    public function  put_aws($products, $store){

        $CSclient =  CloudSearchDomainClient::factory(array('credentials' => array('key' => env('AWSCloudKey'),
                                                                         'secret' => env('AWSSecret'),),
                                                                         'endpoint' => $this->cloud_doc_endpoint($store),
                                                                         'validation' => false,
                                                                         'version' => 'latest',));

        $products = $this->make_aws_format($products);

       /* if($store == 1230){
            $json = json_encode($products);
            die(print_r($json));
        }
        */
       // print_r($products);


        $result = $CSclient->uploadDocuments([
            'documents' => \GuzzleHttp\json_encode($products),
            'contentType' =>'application/json'
        ]);

       print_r($result);
    }

    public function cloud_doc_endpoint($store){

        $creditials['test'] = 'https://doc-aws-test-5frdmdrifkfk4fv6bffljyjd5m.us-west-1.cloudsearch.amazonaws.com';
        $creditials[2701] = 'https://doc-store-2701-nsp7r26btfm5bmj7pdunqksdhq.us-west-1.cloudsearch.amazonaws.com';
        $creditials[1230] = 'https://doc-store-1230-yuidmmfr7a53ecxlwa6j2moafm.us-west-1.cloudsearch.amazonaws.com';
        $creditials[9515] = 'https://doc-store-9515-5lzbu7736t3sjqnuvi4a5m5xje.us-west-1.cloudsearch.amazonaws.com';
        $creditials[1006] = 'https://doc-store-1006-abkghiu3osli562jxhevzs52w4.us-west-1.cloudsearch.amazonaws.com';
        $creditials[3501] = 'https://doc-store-3501-ngeuq4bh7ihwwfwusextktqw2u.us-west-1.cloudsearch.amazonaws.com';

        return $creditials[$store];
    }


    public function make_aws_format($products){

       foreach ($products as $p){
                $batch[] = array('type' => 'add',
                            'id'  => $p->upc.$p->store_number,
                            'fields' => $this->format_fields($p));
            }
       return $batch;
    }


    /**
     * @param $row
     * @return array
     * removes blank fields for upload and converts keywords to an array
     */
    public function format_fields($row){
        $fields = array();
        $row = $this->correct_description($row);
        print_r($row);
        foreach($row as $key => $value){
            if($value != ''){
                if(in_array($key, array('id','people', 'kwikee_brand_name'))){
                   continue;
                }
                if($key == 'image'){
                    $fields[$key] = "https://bfl-corp-sara.s3.us-west-2.amazonaws.com/product-image/".trim($value);
                    continue;
                }
                if ($key == 'keywords'){
                     if($value != ''){
                         $value = explode(',',$value);
                         $fields[$key] = $value;
                     }
                }else {
                    $fields[$key] = strip_tags(trim($value));
                }
            }

        }
        return $fields;
    }

    public function correct_description($row){
        if($row->brand_name =='' && $row->kwikee_brand_name != ''){
            $row->brand_name = $row->kwikee_brand_name;
        }
        if($row->description =='' && $row->kwikee_description != ''){
            $row->description = $row->kwikee_description;
        }
        if($row->size =='' && $row->kwikee_size != ''){
            $row->size = $row->kwikee_size;
        }
        if($row->description == '' && $row->shopping_desc !=''){
            $row->description = $row->shopping_desc;
        }
        if($row->name == '' && $row->kwikee_name != ''){
            $row->name = $row->kwikee_name;
        }
        return $row;
    }

}
