<?php


class OgenzoPayments
{

    public $methodmtn, $methodairtel, $user_name, $password;
    public function __construct($data)
    {
     
        $this->dir = plugin_dir_path(__FILE__) . "ogenzo.txt";
        $this->methodmtn = $data['mtn'];
        $this->methodairtel = $data['airtel'];
        $this->user_name = $data['user_name'];
        $this->password = $data['api_password'];
    }

    function createToken()
    {
       
        $log = [
            'email' => $this->user_name,
            'password' => $this->password,
        ];
        $url = 'https://payments.ogenzo.com/api/v1/';
        $creditials = json_encode($log);
        $hh[] = "Content-Type: application/json";
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . 'login');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $creditials);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hh);
        $response = curl_exec($ch);
        curl_close($ch);
      
        $r = json_decode($response);
        return  $r->data->token ?? null;
    }

    public function getToken()
    {

        try {
            $token_file = (array)  unserialize(file_get_contents($this->dir));

            if (strtotime("now") > $token_file['expiry']) {
                
                $token = $this->createToken();
                error_log("token generated " . $token, null, __DIR__);
                try {
                    $open = fopen($this->dir, "w");
                     fputs($open, serialize(['token' => $token, 'expiry' => strtotime("+23 hours")]));
                    fclose($open);
                } catch (\Throwable $th) {
                    //throw $th;
                }
            } else {

                $token = $token_file['token'];
            }

            return $token;
        } catch (\Throwable $th) {
          
        }
    }

    public function determineMethod($phone)
    {
        switch ($phone) {
            case (preg_match('/(077)[0-9]{7}/', $phone) ? true : false):
                # mtn...
                return $this->methodmtn;
                break;
            case (preg_match('/(078)[0-9]{7}/', $phone) ? true : false):
                # mtn...
                return $this->methodmtn;
                break;
            case (preg_match('/(039)[0-9]{7}/', $phone) ? true : false):
                # mtn...
                return $this->methodmtn;
                break;
            case (preg_match('/(075)[0-9]{7}/', $phone) ? true : false):
                # airtel...
                return $this->methodairtel;
                break;
            case (preg_match('/(070)[0-9]{7}/', $phone) ? true : false):
                # airtel...
                return $this->methodairtel;
                break;
            default:
                # code...
                return $this->methodmtn;
                break;
        }
    }

    public function Collect(array $data)
    {

        $data = (object) $data;

        $send = [
            'slug' => $this->determineMethod($data->phone),
            'amount' => $data->amount,
            'comfirm' => true,
            'phone' => $data->phone,
            'ref' =>   $data->txn_id,
            'msg' => $data->msg,
            'note' => $data->msg
        ];
        $payload = json_encode($send);
        $url = 'https://payments.ogenzo.com/api/v1/';
        $hh[] = "Content-Type: application/json";

        if ($this->getToken() == null) {

            return (object) ['status' => 'false'];
        }

        if (($this->getToken() != null)) {
            $hh[] = 'Authorization: Bearer ' . $this->getToken();
          
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . 'user/wallet/deposit');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $hh);

            try {
                $dref = curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);
                $ss = json_decode($dref);
              
            } catch (\Throwable $th) {
                return (object) ['status' => 'false'];
            }
            return (object) ['status' => 'true'];
        }
    }


    public function DepositStatus($data)
    {
        $data = (object) $data;
        $send = [
            'slug' => $data->slug,
            'ref' =>   $data->txn_id,
        ];
        $payload = json_encode($send);
       
        if ($this->getToken() == null) {

            return (object) ['status' => 'failed'];
        }
        $url = 'https://payments.ogenzo.com/api/v1/';
        $hh[] = "Content-Type: application/json";
        if (($this->getToken() != null)) {
            $hh[] = 'Authorization: Bearer ' . $this->getToken();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . 'user/wallet/deposit/status');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $hh);
            $dref = curl_exec($ch);
            curl_close($ch);
            $ss = json_decode($dref);
            try {
                return $ss->data->status;
            } catch (\Throwable $th) {
                return 'state_note_determined';
            }
        }
    }
}
