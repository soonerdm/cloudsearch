<?php

namespace App\Console\Commands;

use App\ShoppingProduct;
use Aws\CloudSearchDomain\CloudSearchDomainClient;
use Illuminate\Console\Command;

class DeleteAWSSearchDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:deleteDocs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will delete documents that are no longer in our shopping database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    private function stores(){
        return array('2701','1230','9515','1006','3501');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //loop through stores and delete documents no longer in our database
       foreach ($this->stores() as $store){
            $this->delete_store_docs($store);
       }
    }

    public function delete_store_docs($store){
        $dproducts = array();
        $batch = array();

        $url = $this->initial_url($store);

        // return initial products
        $products = $this->guzzle_connect($url);

        $p = json_decode($products, true);

        // cursor holds place in loop
        $cursor =  $p['hits']['cursor'];

        foreach($p['hits']['hit'] as $key => $i){
            if(!$this->in_shopping($i['id'], $i['fields']['store_number'], $i['fields']['upc'])) {
                $dproducts[] = $i['id'];
                echo "- ".$i['id'] ."- ";
            }
            if ($key >= 9999){
                $more = true;
                break;
            }

        }

        if(isset($more)){
            $loop_url = $this->loop_url($store, $cursor);
            $products = $this->guzzle_connect($loop_url);

            $p = json_decode($products, true);

            foreach($p['hits']['hit'] as $key => $i) {
                if (!$this->in_shopping($i['id'], $i['fields']['store_number'], $i['fields']['upc'])) {
                    $dproducts[] = $i['id'];
                    echo "- ".$i['id'] ."- ";
                }
            }
        }
        if(!empty($dproducts)) {
            foreach ($dproducts as $d) {
                $batch[] = array('type' => 'delete',
                    'id' => $d);
            }
        }

        if(!empty($batch)) {
//            echo " -------------- file --------------- \r\n";
//            print_r($batch);
//            echo " --------------End File ------------ \r\n";

            $CSclient = CloudSearchDomainClient::factory(array('credentials' => array('key' => env('AWSCloudKey'),
                'secret' => env('AWSSecret'),),
                'endpoint' => $this->cloud_doc_endpoint($store),
                'validation' => false,
                'version' => 'latest',));

            $result = $CSclient->uploadDocuments([
                'documents' => \GuzzleHttp\json_encode($batch),
                'contentType' => 'application/json'
            ]);
        }

    }

    public function guzzle_connect($url){
        $client = new \GuzzleHttp\Client();
        $response = $client->get($url);
        $answer = $response->getBody()->getContents();

        return $answer;
    }

    //$id = aws id to match against shopping_products
    // false if not in the table
    public function in_shopping($id, $store, $upc)
    {
        $r = ShoppingProduct::where('sku', '=', $upc)->where('store_number', '=', $store)->first();
        if ($r) {
            return true;
        } else {
            return false;
        }
    }

    public function initial_url($store){
        $url[1230] = 'https://search-store-1230-yuidmmfr7a53ecxlwa6j2moafm.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor=initial&size=10000';
        $url[9515] = 'https://search-store-9515-5lzbu7736t3sjqnuvi4a5m5xje.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor=initial&size=10000';
        $url[3501] = 'https://search-store-3501-ngeuq4bh7ihwwfwusextktqw2u.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor=initial&size=10000';
        $url[1006] = 'https://search-store-1006-abkghiu3osli562jxhevzs52w4.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor=initial&size=10000';
        $url[2701] = 'https://search-store-2701-nsp7r26btfm5bmj7pdunqksdhq.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor=initial&size=10000';
        $url['test'] = 'https://search-aws-test-5frdmdrifkfk4fv6bffljyjd5m.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor=initial&size=10000';

        return $url[$store];
    }

    public function loop_url($store, $cursor){
        $url[9515] = 'https://search-store-9515-5lzbu7736t3sjqnuvi4a5m5xje.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor='.$cursor.'&size=10000';
        $url[1230] = 'https://search-store-1230-yuidmmfr7a53ecxlwa6j2moafm.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor='.$cursor.'&size=10000';
        $url[3501] = 'https://search-store-3501-ngeuq4bh7ihwwfwusextktqw2u.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor='.$cursor.'&size=10000';
        $url[1006] = 'https://search-store-1006-abkghiu3osli562jxhevzs52w4.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor='.$cursor.'&size=10000';
        $url[2701] = 'https://search-store-2701-nsp7r26btfm5bmj7pdunqksdhq.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor='.$cursor.'&size=10000';
        $url['test'] = 'https://search-aws-test-5frdmdrifkfk4fv6bffljyjd5m.us-west-1.cloudsearch.amazonaws.com/2013-01-01/search?q=matchall&q.parser=structured&cursor='.$cursor.'&size=10000';

        return $url[$store];

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



}
