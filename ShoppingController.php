<?php

namespace App\Http\Controllers;

use App\Mail\ContactForm;
use App\Mail\FeedbackForm;
use App\Mail\NotesForm;
use App\Note;
use App\Order;
use App\User;
use Faker\Provider\DateTime;
use Gloudemans\Shoppingcart\Facades\Cart;
use http\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Meta;

class ShoppingController extends Controller
{
    public function __construct()
    {

        $this->middleware('store', ['except' => ['notes', 'notes_form']]);

        $this->middleware(function ($request, \Closure $next) {

        $this->store = Session::get('store');

        if (empty(Session::get('categories'))) {

            $this->categories = json_decode(file_get_contents(env('BFL_API_URL') . '/shopping-categories-list/' . Session::get('store')));
            $this->categories = Session::put('categories', $this->categories);
        }
        else {
            $this->categories = Session::get('categories');
        }


            $brand = [
                '3501' => 'Buy For Less',
                '7957' => 'Buy For Less',
                '2701' => 'SuperMercado',
                '3701' => 'SuperMercado',
                '3713' => 'SuperMercado',
                '4150' => 'SuperMercado',
                '1230' => 'Uptown Grocery Co.',
                '9515' => 'Uptown Grocery Co.',
                '1006' => 'Smart Saver',
                '1205' => 'Smart Saver',
                '1201' => 'Smart Saver',
                '2001' => 'Smart Saver',
                '4424' => 'Smart Saver'
            ];

            /*Default meta tags*/
            Meta::title($brand[$this->store] . ' Online Shopping');
            Meta::set('site_name', $brand[$this->store] . ' Online Shopping');
            Meta::set('robots', 'index,follow');

            $response = $next($request);

            if(isset($response)) {
                return $response;
            }

        }, ['except' => ['notes', 'notes_form']]);
    }

    /**
     *  Home View
     *
     *
     */

    public function index()
    {
        Meta::set('title', 'Home');
        Meta::set('description', 'Online Shopping - Home');

        $categories = $this->categories();

        $brand = [
            '3501' => 'Buy For Less',
            '7957' => 'Buy For Less',
            '2701' => 'SuperMercado',
            '3701' => 'SuperMercado',
            '3713' => 'SuperMercado',
            '4150' => 'SuperMercado',
            '1230' => 'Uptown Grocery Co.',
            '9515' => 'Uptown Grocery Co.',
            '1006' => 'Smart Saver',
            '1205' => 'Smart Saver',
            '1201' => 'Smart Saver',
            '2001' => 'Smart Saver',
            '4424' => 'Smart Saver'
        ];

        $store = $brand[Session::get('store')];

        $url = '';

        switch ($store) {
            case 'Uptown Grocery Co.':
                $url = 'https://marketing.buyforlessok.com/api/gallery/3';
                break;
            case 'Buy For Less':
                $url = 'https://marketing.buyforlessok.com/api/gallery/4';
                break;
            case 'Smart Saver':
                $url = 'https://marketing.buyforlessok.com/api/gallery/5';
                break;
            case 'SuperMercado':
                $url = 'https://marketing.buyforlessok.com/api/gallery/6';
                break;
        }

        $gallery = file_get_contents($url);

        $gallery = json_decode($gallery);

        return view('pages.home', compact(['categories', 'gallery']));
    }

    /**
     * categories
     */

    public function categories()
    {
        if (empty(Session::get('categories'))) {

            $categories = json_decode(file_get_contents(env('BFL_API_URL') . '/shopping-categories-list/' . Session::get('store')));

            $categories = Session::put('categories', $categories);
        } else {
            $categories = Session::get('categories');
        }

        return $categories;
    }


/**
     *  Category View
     *
     *
     */

    public function category($id)
    {
        $categories = $this->categories();

        $id = explode("-", $id)[0];

        $url = env('BFL_API_URL') . '/shopping-category-view/' . $this->store . '/' . $id;

        if(isset($_GET['page']))
        {
            $url .= '?page=' . $_GET['page'];
        }

        $products = file_get_contents($url);

        $products = json_decode($products);

        Meta::set('title', $products->category->name);
        Meta::set('description', 'Online Shopping - ' . $products->category->name);

        return view('pages.category', compact('products', 'categories'));
    }

    /**
     *  Product View
     *
     *
     */

    public function product($id)
    {
        $categories = $this->categories();

        $id = explode('-', $id)[0];

        $product = file_get_contents(env('BFL_API_URL') . '/shopping-product-view/' . $this->store . '/' . $id);

        $product = json_decode($product)[0];

        Meta::set('title', $product->brand_name . ' ' . $product->name);
        Meta::set('description', 'Online Shopping - ' . $product->brand_name . ' ' . $product->name);

        return view('pages.product', compact('categories', 'product'));
    }

    /**
     *  Search View
     *
     *
     */

    public function search()
    {
        $categories = $this->categories();

        $products = [];

        if(isset($_GET['keyword']))
        {
            $keyword = $_GET['keyword'];

            $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
            $keyword = preg_replace($regexEmoticons, '', $keyword);
            $keyword = str_replace( array( '\'', '"', ',' , ';', '<', '>', '*', '-' ), '', $keyword);


            if(trim($keyword) !== "" && strstr($keyword, '/') == FALSE) {

                Session::forget('search_string');

                Session::put('search_string', $keyword);

                $url = env('BFL_API_URL') . '/shopping-product-search/' . $this->store . '/' . urlencode(trim($keyword));

            //  $url = 'http://bfl-marketing.test:8000/api/shopping-product-search/'.$this->store.'/'.urlencode(trim($keyword));

                if(isset($_GET['page']))
                {
                    $url .= '?page=' . $_GET['page'];
                }

                try {

                    $client = new \GuzzleHttp\Client();
                    $response = $client->get($url);
                    $answer = $response->getBody()->getContents();
                    $products = json_decode($answer);

                } catch (\Exception $e) {
                    $products = '';
                }

                Meta::set('title','Search Results for &quot;' . $keyword . '&quot;');
                Meta::set('description', 'Search Results for &quot;' . $keyword . '&quot;');

                return view('pages.search', compact('products', 'categories'));

            } else {
                Session::flash('message', [
                    'type'    => 'danger',
                    'message' => 'Your search contained characters that are not allowed or the field was left blank. Please try again.'
                ]);

                return back();
            }
        }


    }

    public function autocomplete(Request $request){

        if($request->get('query')){

         $query = $request->get('query');

         if(strlen($query) > 2) {

             $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
             $query = preg_replace($regexEmoticons, '', $query);
             $query = str_replace( array( '\'', '"', ',' , ';', '<', '>', '*', '-' ), '', $query);

             $url = env('BFL_API_URL') . '/shopping-product-search/' . $this->store . '/' . urlencode(trim($query));
             //$products = file_get_contents($url);

             try {

                 $client = new \GuzzleHttp\Client();
                 $response = $client->get($url);
                 $answer = $response->getBody()->getContents();
                 $products = json_decode($answer);

             } catch (\Exception $e) {
                 $products = '';
             }


             $output = "<div class='search_container' style='border: #3c763d solid 1px;'>";
             if (!empty($products)) {
                 foreach ($products as $p) {
                     //die(print_r($p->data));
                     foreach ($p->data as $d) {
                         $name = '';
                         if (substr($d->brand_name, 0, 13) == '1 LB is about')
                         {
                             $name = '<a href="/shopping/product/' . $d->upc . '"><b>' . $d->name . '</b></a> <br>' . $d->size . ' | ';
                         } else {
                             $name = '<a href="/shopping/product/' . $d->upc . '"><b>' . $d->brand_name . ' ' . $d->name . '</b></a> <br>' . $d->size . ' | ';
                         }

                         $output .= '<div class="product_container" style="background-color: #ffffff; padding:10px; border-bottom: 1px solid #dddddd">';
                         $output .= $name;
                         $output .= ($d->sale_price != '' ? '<small><span class="label label-danger">On Sale!</span></small> $' . $d->sale_price : '$' . $d->regular_price);
                         $output .= '</div>';
                     }
                 }

                 $output .= '</div>';
                 echo $output;

                 if (strlen($query) > 1) {
                     echo "";
                 }
             }
         }
        }
    }


    /**
     *  Contact View
     *
     *
     */

    public function contact()
    {
        $categories = $this->categories();

        Meta::set('title','Contact Us');
        Meta::set('description', 'Contact Us');

        return view('pages.contact', compact('categories'));
    }

    /**
     *  Contact Form
     *
     *
     */

    public function contact_form(Request $request)
    {
        $this->validate($request, [
            'name'    => 'required',
            'email'   => 'required|email',
            'phone'   => 'required',
            'contact_preference' => 'required',
            'message' => 'required',
            'g-recaptcha-response' => 'required|recaptcha'
        ]);

        Mail::to('digital@buyforlessok.com')->send(new ContactForm($request));

        Session::flash('message', [
            'type'    => 'success',
            'message' => 'Thank you! Your message has been received. We\'ll be in contact soon.'
        ]);

        return back();
    }

    /**
     *  Feedback View
     *
     *
     */

    public function feedback()
    {
        $categories = $this->categories();

        Meta::set('title','Feedback - Tell us about your experience');
        Meta::set('description', 'Feedback - Tell us about your experience');

        return view('pages.feedback', compact('categories'));
    }

    /**
     *  Feedback Form
     *
     *
     */

    public function feedback_form(Request $request)
    {
        $this->validate($request, [
            'name'    => 'required',
            'email'   => 'required|email',
            'experience' => 'required',
            'message' => 'required',
            'g-recaptcha-response' => 'required|recaptcha'
        ]);

        Mail::to('digital@buyforlessok.com')->send(new FeedbackForm($request));

        Session::flash('message', [
            'type'    => 'success',
            'message' => 'Thank you! Your feedback has been received.'
        ]);

        return back();
    }

    /**
     *  Reset Location
     *
     *
     */

    public function reset_location()
    {
        Cart::destroy();

        Session::forget('pickup_time');
        Session::forget('pickup_date');
        Session::forget('is_complex');
        Session::forget('store');
        Session::forget('categories');
        Session::forget('delivery_date');
        Session::forget('delivery_time');

        return back();
    }


    public function delivery_times($date, $location){
        $hours = ['10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00','13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30'];

        $slots = '<option value="">Select...</option>';

        foreach ($hours as $hour)
        {
            $count = count( Order::where('pickup_date', $date)->where('pickup_time', $hour)->where('store',$location)->get() );

            if($count < 2)
            {
                if($date == date('Y-m-d'))
                {
                    $min = strtotime('+4 hours');

                    if(strtotime($hour) > $min)
                    {
                        $slots .= "<option value='".date('H:i',strtotime($hour.'+1 hour'))."'>" . date('g:i A', strtotime($hour)) . " - " . date('g:i A', strtotime($hour. ' + 1 hour '))."</option>";
                    }
                }

                if($date > date('Y-m-d'))
                {
                    $slots .= "\"<option value='".date('H:i',strtotime($hour.'+1 hour'))."'>" . date('g:i A', strtotime($hour)) . " - " . date('g:i A', strtotime($hour. ' + 1 hour ')) . "</option>";
                }
            }
        }

        return $slots;

    }


    /**
     *  Pickup Times
     *
     *
     */

    public function pickup_times($date, $location)
    {
        $hours = ['10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00','13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30'];

        $slots = '<option value="">Select...</option>';

        foreach ($hours as $hour)
        {
            $count = count( Order::where('pickup_date', $date)->where('pickup_time', $hour)->where('store',$location)->get() );

            if($count < 2)
            {
                if($date == date('Y-m-d'))
                {
                    $min = strtotime('+4 hours');

                    if(strtotime($hour) > $min)
                    {
                        $slots .= "<option value='$hour'>" . date('g:i A', strtotime($hour)) . "</option>";
                    }
                }

                if($date > date('Y-m-d'))
                {
                    $slots .= "<option value='$hour'>" . date('g:i A', strtotime($hour)) . "</option>";
                }
            }
        }

        return $slots;
    }

    /**
     *  Pickup Settings
     *
     *
     */

    public function pickup_settings(Request $request)
    {
        if( $request->is_complex == 1 )
        {
            $this->validate($request, [
                'complex'    => 'required'
            ]);

            $complex = $request->complex;

            $next = strtotime('next thursday');

            if (date('Y-m-d') < '2018-11-22') {
                $next = strtotime('next wednesday');
            }

            Session::put('is_complex', 1);
            Session::put('complex', $request->complex);

            if ( $complex == 'J Marshall Square' || $complex == 'The Classen' || $complex == 'The Montgomery' )
            {
                Session::put('pickup_date', date('Y-m-d', $next));
                Session::put('pickup_time', '14:00');
            } elseif ( $complex = 'Park Harvey Apartments') {
                Session::put('pickup_date', date('Y-m-d', $next));
                Session::put('pickup_time', '15:00');
            }


        }else{

            if( $request->pickup_time !== 'Select...')
            {
                $this->validate($request, [
                    'pickup_time' => 'required',
                    'pickup_date' => 'required'
                ]);

                Session::put('pickup_time', $request->pickup_time);
                Session::put('pickup_date', $request->pickup_date);

                Session::flash('message', [
                    'type' => 'success',
                    'message' => 'Your time slot has been reserved.'
                ]);
            } else {
                Session::flash('message', [
                    'type'    => 'danger',
                    'message' => 'You did not specify a pickup time. Please try again.'
                ]);
            }
        }

        return back();
    }

    /**
     *  Pickup Reset
     *
     *
     */

    public function pickup_reset()
    {
        Session::forget('pickup_time');
        Session::forget('pickup_date');
        Session::forget('is_complex');

        Session::flash('message', [
            'type'    => 'success',
            'message' => 'Your time slot has been reset. Please select a new one before completing your order.'
        ]);

        return back();
    }

    public function delivery_reset(){
        Session::forget('delivery_date');
        Session::forget('delivery_time');

        Session::flash('message', [
            'type'    => 'success',
            'message' => 'Your delivery time has been reset. Please select a new one before completing your order.'
        ]);

        return back();

    }

    /**
     *  Terms
     *
     *
     */

    public function terms()
    {
        $categories = $this->categories();

        Meta::set('title','Terms of Use');
        Meta::set('description', 'Terms of Use');

        return view('pages.terms', compact('categories'));
    }

    /**
     *  Add Notes to Order
     *
     *
     */

    public function notes($id)
    {
        $order = Order::findOrFail($id);

        $categories = json_decode(file_get_contents(env('BFL_API_URL') . '/shopping-categories-list/' . $order->store));

        Meta::set('title','Add Notes To Your Order');
        Meta::set('description', 'Add Notes To Your Order');

        return view('pages.notes', compact('order', 'categories'));
    }

    /**
     *  Note Form
     *
     *
     */

    public function notes_form(Request $request, $id)
    {
        $this->validate($request, [
            'message' => 'required',
            'g-recaptcha-response' => 'required|recaptcha'
        ]);

        $order = Order::findOrFail($id);

        if($order->status != 'PLACED')
        {
            Session::flash('message', [
                'type'    => 'danger',
                'message' => 'Sorry. You can not add any message to this order.'
            ]);

            return back();
        }

        $note = new Note();
        $note->note = $request->message;
        $note->order_id = $order->id;
        $note->save();

        Mail::to('digital@buyforlessok.com')
            ->cc(User::where('store', $order->store)->select('email')->get()->map(function($email){ return $email->email; }))
            ->bcc($order->email)
            ->send(new NotesForm($request, $order));

        Session::flash('message', [
            'type'    => 'success',
            'message' => 'Thank you! Your note has been added to your order.'
        ]);

        return redirect()->action('ShoppingController@index');
    }

    /**
     *  Thank you / Confirmation page
     *
     *
     */

    public function thankyou($id)
    {
        $order = Order::findOrFail($id);

        $categories = $this->categories();

        Meta::set('title','Thank You!');
        Meta::set('description', 'Thank you! Your order has been successfully submitted.');

        return view('pages.thankyou', compact('categories', 'order'));
    }
}
