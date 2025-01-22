
 public function createTransaction(Request $request)
    {

        try {

            $rq_uuid = strtoupper(Str::random(6) ."-".Str::random(13));
            $rq_datetime = Carbon::now()->format("Y-m-d H:m:s");
            $order_id = strtoupper(Str::random(10));
            $amount = $request->input('amount');

            $ccy = ENV("ESPAY_CURRENCY");
            $key = ENV("ESPAY_KEY");
            $comm_code = ENV("ESPAY_COMM_ID");
            $remark1 = $request->input("remark1"); //phone
            $remark2 = $request->input("remark2"); //name
            $remark3 = $request->input("remark3"); //email
            $update = $request->input("update");
            $bank_code = $request->input("bank_code");
            $exp = 60 * 24;

            $signature_text = strtoupper("##$key##$rq_uuid##$rq_datetime##$order_id##$amount##$ccy##$comm_code##SENDINVOICE##");
            $signature = hash("sha256",$signature_text);

            $row = new transaction();
            $row->rq_uuid = $rq_uuid;
            $row->ccy = $ccy;
            $row->amount = $amount;
            $row->comm_code = $comm_code;
            $row->remark1 = $remark1;
            $row->remark2 = $remark2;
            $row->remark3 = $remark3;
            $row->update = "N";
            $row->bank_code = $bank_code;
            $row->va_expired = $exp;
            $row->save();

            $data = [
                "rq_uuid" => $rq_uuid,
                "rq_datetime" => $rq_datetime,
                "order_id" => $order_id,
                "amount" => $amount,
                "ccy" => $ccy,
                "comm_code" => $comm_code,
                "remark1" => $remark1,
                "remark2" => $remark2,
                "remark3" => $remark3,
                "update" => "N",
                "bank_code" => $bank_code,
                "va_expired"=> $exp,
                "signature" => $signature,
            ];



            $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => env('ESPAY_URL').'rest/merchantpg/sendinvoice',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
            ));

            // dd($curl);
            $response = curl_exec($curl);

            return response()->json([
                "espay" => json_decode($response),
                "payload" => $data
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                "espay" => json_decode($response),
                "payload" => $data
            ], 200);
        }
    }


    <!--  -->

    public function getMerchantInfo(Request $request)
    {
        $data = [
            "key" => "fda1aa6648f2635b38725fd17761c1f4"
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => env('ESPAY_URL').'rest/merchant/merchantinfo',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        ));

        $response = curl_exec($curl);

        $dataBank = json_decode($response);
        // dd($data->data);
        if (count($dataBank->data)) {

            $newDataBank = array();
            for ($i=0; $i < count($dataBank->data); $i++) {
                if (strpos($dataBank->data[$i]->productName, "VA") !== false) {
                    $dataPush['bankCode'] = $dataBank->data[$i]->bankCode;
                    $dataPush['productCode'] = $dataBank->data[$i]->productCode;
                    $dataPush['productName'] = $dataBank->data[$i]->productName;
                    array_push($newDataBank, $dataPush);
                }
            }
        }

        $newData = json_decode(json_encode($newDataBank), FALSE);
        $dataBank->data = $newData;

        return response()->json([
            "espay" => $dataBank,
            "payload" => $data
        ], 200);
    }

    public function inquiry(Request $request)
    {
        $data = [
            "key" => "fda1aa6648f2635b38725fd17761c1f4"
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => env('ESPAY_URL').'rest/merchant/merchantinfo',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        ));

        $response = curl_exec($curl);

        return response()->json([
            "espay" => json_decode($response),
            "payload" => $data
        ], 200);
    }


    <!--  -->
    public function transactionInquiry(Request $request)
    {

        $key = ENV("ESPAY_KEY");

        $result = EspayTransaction::where("rq_uuid", $request->input("rq_uuid"))->first();

        if(!empty($result)){
            $result->rs_uuid = $request->input("rq_uuid");
            $result->rs_datetime = $request->input("rq_datetime");
            $result->rs_sender_id = $request->input("sender_id");
            $result->rs_receiver_id = $request->input("receiver_id");
            $result->rs_password = $request->input("password");
            $result->rs_comm_code = $request->input("comm_code");
            $result->rs_member_code = $request->input("member_code");
            $result->rs_signature = $request->input("signature");
            $result->rs_logs = json_encode($request->post());

            $signature_text = strtoupper("##$key##".$result->rs_uuid."##".$result->rs_datetime."##".$result->order_id."##0000##INQUIRY-RS##");
            $signature = hash("sha256",$signature_text);

            $response = [
                "rq_uuid" => $result->rs_uuid,
                "rs_datetime" => $result->rs_datetime,
                "error_code" => "0000",
                "error_message" => "Success",
                "signature" => $signature,
                "order_id" => $result->order_id,
                "amount" => $result->rq_amount,
                "ccy" => $result->rq_ccy,
                "description" => "Pembayaran",
                "trx_date" => $result->rq_trx_date,
                "installment_period" => $result->installment_period,
                "customer_details" => $result->rq_customer_details,
                "shipping_address" => $result->shipping_address,
            ];

            $result->rq_logs = json_encode($response);
            $result->save();


        
            $responseData  = [
                "rq_uuid"=>$request->input("rq_uuid"),
                "rs_datetime"=>$request->input("rq_datetime"),
                "error_code"=>"0000",
                "error_message"=>"Success",
                "order_id"=>$result->order_id,
                "amount"=>$result->rq_amount,
                "ccy"=>$result->rq_ccy,
                "description"=>"Pembayaran"
            ];

            return response()->json($responseData, 200);
        }else{
        
            $responseData  = [
                "rq_uuid"=>$request->input("rq_uuid"),
                "rs_datetime"=>$request->input("rq_datetime"),
                "error_code"=>"0014",
                "error_message"=>"invalid order id"
            ];
            return response()->json($responseData, 400);
        }
    }