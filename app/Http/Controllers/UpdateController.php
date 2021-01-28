<?php

namespace App\Http\Controllers;

use App\AcceptDelivery;
use App\Http\Middleware\RedirectIfNoUpdateAvailable;
use App\Install\Requirement;
use App\Orderstatus;
use App\PaymentGateway;
use App\Setting;
use App\SmsGateway;
use App\Translation;
use App\User;
use Artisan;
use Auth;
use Barryvdh\Debugbar\Facade as Debugbar;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Ixudra\Curl\Facades\Curl;
use Session;
use Zipper;

class UpdateController extends Controller
{

    public function __construct()
    {
        Debugbar::disable();
        $this->middleware(RedirectIfNoUpdateAvailable::class);
    }

    public function updatePage(Requirement $requirement)
    {
        $updateFile = File::get(storage_path('update'));
        if ($updateFile) {
            $updateVersion = 'v' . $updateFile;
        } else {
            $updateVersion = null;
        }

        $extensionSatisfied = true;
        foreach ($requirement->extensions() as $label => $satisfied) {
            if (!$satisfied) {
                $extensionSatisfied = false;
                break;
            }
        }

        $permissionSatisfied = true;
        foreach ($requirement->directories() as $label => $satisfied) {
            if (!$satisfied) {
                $permissionSatisfied = false;
                break;
            }
        }

        return view('install.update', array(
            'updateVersion' => $updateVersion,
            'extensionSatisfied' => $extensionSatisfied,
            'permissionSatisfied' => $permissionSatisfied,
            'requirement' => $requirement,
        ));
    }

    /**
     * @param Request $request
     */
    public function update(Request $request)
    {
        $admin = User::where('id', '1')->first();
        $hashedPassword = $admin->password;

        if (!Hash::check($request->password, $hashedPassword)) {
            return redirect()->back()->with(['message' => 'Access Denied']);
        } else {
            // process update here...
            $start_time = microtime(true);
            $start_time2 = microtime(true);
            sleep(5); //manual delay

            try {

                $duplicates = AcceptDelivery::whereIn('order_id', function ($query) {
                    $query->select('order_id')->from('accept_deliveries')->groupBy('order_id')->havingRaw('count(*) > 1');
                })->get();

                foreach ($duplicates as $duplicate) {

                    if ($duplicate->is_completed == 0 && ($duplicate->order->orderstatus_id == 5 || $duplicate->order->orderstatus_id == 6)) {
                        //just delete
                        $duplicate->delete(); //delete the duplicate entry in db
                    }

                    if ($duplicate->is_completed == 0 && $duplicate->order->orderstatus_id == 3) {
                        //delete and change orderstatus to 2
                        $duplicate->order->orderstatus_id = 2; //change order status to not delivery assigned
                        $duplicate->order->save(); //save the order
                        $duplicate->delete(); //delete the duplicate entry in db
                    }

                }

                // ** MIGRATE ** //
                //first migrate the db if any new db are avaliable...
                Artisan::call('migrate', [
                    '--force' => true,
                ]);
                // ** MIGRATE END ** //

                // ** SETTINGS ** //
                // get the latest settings json file from the server...
                $data = Curl::to('https://stackcanyon.com/products/foodoma/updates/settings.json')->get();
                $data = json_decode($data);
                if (!$data) {
                    $data = file_get_contents(storage_path('data/data.json'));
                    $data = json_decode($data);
                }
                $dbSet = [];
                foreach ($data as $s) {
                    //check if the setting key already exists, if exists, do nothing..
                    $settingAlreadyExists = Setting::where('key', $s->key)->first();
                    //else create an array of settings which doesnt exists...
                    if (!$settingAlreadyExists) {
                        $dbSet[] = [
                            'key' => $s->key,
                            'value' => $s->value,
                        ];
                    }
                }
                //insert new settings keys into settings table.
                DB::table('settings')->insert($dbSet);
                // ** SETTINGS END ** //

                // ** PAYMENTGATEWAYS ** //
                // check if paystack is installed
                $hasPayStack = PaymentGateway::where('name', 'PayStack')->first();
                if (!$hasPayStack) {
                    //if not, then install new payment gateway "PayStack"
                    $payStackPaymentGateway = new PaymentGateway();
                    $payStackPaymentGateway->name = 'PayStack';
                    $payStackPaymentGateway->description = 'PayStack Payment Gateway';
                    $payStackPaymentGateway->is_active = 0;
                    $payStackPaymentGateway->save();
                }
                // check if razorpay is installed
                $hasRazorPay = PaymentGateway::where('name', 'Razorpay')->first();
                if (!$hasRazorPay) {
                    //if not, then install new payment gateway "Razorpay"
                    $razorPayPaymentGateway = new PaymentGateway();
                    $razorPayPaymentGateway->name = 'Razorpay';
                    $razorPayPaymentGateway->description = 'Razorpay Payment Gateway';
                    $razorPayPaymentGateway->is_active = 0;
                    $razorPayPaymentGateway->save();
                }
                // ** END PAYMENTGATEWAYS ** //

                $hasPayMongo = PaymentGateway::where('name', 'PayMongo')->first();
                if (!$hasPayMongo) {
                    //if not, then install new payment gateway "PayMongo"
                    $payMongoPaymentGateway = new PaymentGateway();
                    $payMongoPaymentGateway->name = 'PayMongo';
                    $payMongoPaymentGateway->description = 'PayMongo Payment Gateway';
                    $payMongoPaymentGateway->is_active = 0;
                    $payMongoPaymentGateway->save();
                }

                $hasMercadoPago = PaymentGateway::where('name', 'MercadoPago')->first();
                if (!$hasMercadoPago) {
                    //if not, then install new payment gateway "MercadoPago"
                    $mercadoPagoPaymentGateway = new PaymentGateway();
                    $mercadoPagoPaymentGateway->name = 'MercadoPago';
                    $mercadoPagoPaymentGateway->description = 'MercadoPago Payment Gateway';
                    $mercadoPagoPaymentGateway->is_active = 0;
                    $mercadoPagoPaymentGateway->save();
                }

                $hasPaytm = PaymentGateway::where('name', 'Paytm')->first();
                if (!$hasPaytm) {
                    //if not, then install new payment gateway "MercadoPago"
                    $paytmPaymentGateway = new PaymentGateway();
                    $paytmPaymentGateway->name = 'Paytm';
                    $paytmPaymentGateway->description = 'Paytm Payment Gateway';
                    $paytmPaymentGateway->is_active = 0;
                    $paytmPaymentGateway->save();
                }

                $hasMsg91 = SmsGateway::where('gateway_name', 'MSG91')->first();
                if (!$hasMsg91) {
                    //if not, then install new sms gateway gateway "MSG91"
                    $msg91Gateway = new SmsGateway();
                    $msg91Gateway->gateway_name = 'MSG91';
                    $msg91Gateway->save();
                }

                $hasTwilio = SmsGateway::where('gateway_name', 'TWILIO')->first();
                if (!$hasTwilio) {
                    //if not, then install new sms gateway gateway "TWILIO"
                    $twilioGateway = new SmsGateway();
                    $twilioGateway->gateway_name = 'TWILIO';
                    $twilioGateway->save();
                }

                DB::table('orderstatuses')->truncate();
                DB::statement("INSERT INTO `orderstatuses` (`id`, `name`) VALUES (1, 'Order Placed'), (2, 'Preparing Order'), (3, 'Delivery Guy Assigned'), (4, 'Order Picked Up'), (5, 'Delivered'), (6, 'Canceled'), (7, 'Ready For Pick Up'), (8, 'Awaiting Payment'), (9, 'Payment Failed')");

                /* Save new keys for translations languages */
                $langData = file_get_contents(storage_path('language/english.json'));
                $a1 = json_decode($langData, true);

                $translations = Translation::all();

                foreach ($translations as $translation) {
                    //get the existing data of a translated language
                    $a2 = json_decode($translation->data, true);

                    //get the difference between the master file and the existing translation, and get the non-existing key
                    $diff = array_diff_key($a1, $a2);

                    //merge the non existing keys with the existing translation
                    $merged = array_merge($a2, $diff);

                    //save the translation
                    $translation->data = json_encode($merged);
                    $translation->save();
                }
                /* END Save new keys for translations languages */

                /** CLEAR LARAVEL CACHES **/
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                /** END CLEAR LARAVEL CACHES **/

                $end_time = microtime(true);
                $execution_time = ($end_time - $start_time);

                if ($execution_time < 12) {
                    sleep(12 - $execution_time);
                }
                $end_time2 = microtime(true);
                $execution_time2 = ($end_time2 - $start_time2);
                // dd($execution_time2);

                //delete update file
                unlink(storage_path('update'));

                //login admin user
                $user = User::where('id', 1)->first();
                if ($user) {
                    if (Auth::attempt(['email' => $user->email, 'password' => $request->password])) {
                        //redirect to admin dashboard
                        return redirect()->route('admin.dashboard')->with(['success' => 'Update Successful']);
                    } else {
                        //or redirect to login page
                        return redirect()->route('get.login')->with(['success' => 'Update Successful']);
                    }
                } else {
                    //or redirect to login page
                    return redirect()->route('get.login')->with(['success' => 'Update Successful']);
                }

            } catch (\Illuminate\Database\QueryException $qe) {
                return redirect()->back()->with(['message' => $qe->getMessage()]);
            } catch (Exception $e) {
                return redirect()->back()->with(['message' => $e->getMessage()]);
            } catch (\Throwable $th) {
                return redirect()->back()->with(['message' => $th]);
            }

        }

    }
}
