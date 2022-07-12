<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as FacadeResponse;
use DB;
use Session;

class PagosController extends Controller
{
    protected $pagadito;

    public function __construct()
    {
        include_once('lib/Pagadito.php');

        define("UID", "1d759d1ffb903f0994847d0ad6c7fab7");
        define("WSK", "34eb16028c0291c97d2dce55a0c67302");

        $this->pagadito  = new Pagadito(UID, WSK);
    }


    public function sendPago(Request $request){

        $nis = $request["num_suministro"];
        $n_factura = $request["numero_factura"];
        $monto = $request["totalpagar"];
        $periodo = $request["periodo"];

        include_once('lib/Pagadito.php');

        define("UID", "1d759d1ffb903f0994847d0ad6c7fab7");
        define("WSK", "34eb16028c0291c97d2dce55a0c67302");
        //define("SANDBOX", true);

        $Pagadito = new Pagadito(UID, WSK);

        $token = '';

        $n_aprobacion = '';

        if($Pagadito->connect()){
            $token = $Pagadito->get_rs_value();

            Session::put('token', $token);

            $Pagadito->add_detail("1", "Pago de NIS : ".$nis." periodo ".$periodo."", $monto);

            $Pagadito->set_custom_param("param1", $nis);
            $Pagadito->set_custom_param("param2", $n_factura);
            $Pagadito->set_custom_param("param3", $periodo);

            $Pagadito->enable_pending_payments();

            $ern = $nis."_".$periodo;
            if (!$Pagadito->exec_trans($ern)) {
                switch($Pagadito->get_rs_code())
                {
                    case "PG2001":
                        /*Incomplete data*/
                    case "PG3002":
                        /*Error*/
                    case "PG3003":
                        /*Unregistered transaction*/
                    case "PG3004":
                        /*Match error*/
                    case "PG3005":
                        /*Disabled connection*/
                    default:

                     $n_aprobacion = $Pagadito->get_rs_reference();
                     return response()->json($Pagadito->exec_trans($ern));

                    break;
                }
            }

        }
        else{
            return response()->json("false");
        }

    }




    public function getStatus(Request $request){
        $id_respuesta = $request["id"];
        $fecha = $request["event_create_timestamp"];
        $resource = $request["resource"];

        $pagado = $resource["amount"];


        $token = $resource["token"];
        $estado = $resource["status"];
        $referencia = $resource["reference"];
        $ern = $resource["ern"];
        $num_suministro = substr($resource["ern"], 0, -7);
        $periodo = substr($resource["ern"], 8);
        $monto_factura = $pagado["total"];


        $insert = app('firebase.firestore')->database()->collection('datos_facturacion')->newDocument();
        $insert->set([
            "ern" => $ern,
            "estado" =>$estado,
            "factelec" => 0,
            "fecha" => $fecha,
            "fuente_registro"=>"Pagadito",
            "id_respuesta"=>$id_respuesta,
            "monto_factura"=>  $monto_factura ,
            "num_suministro"=> $num_suministro,
            "periodo"=>$periodo,
            "referencia"=>$referencia,
            "token"=>  $token
        ]);


        return response()->json($insert);





    }




}
