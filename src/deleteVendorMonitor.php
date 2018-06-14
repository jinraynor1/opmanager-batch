<?php

/**
 * Elimina un monitor del vendor, realiza funciones curl debido a la inexistencia de un metodo del api
 * que permite eliminar monitores del vendor necesita un usuario y password valido con suficientes permisos
 * para poder realizar la operacion, esta funcion tambien cambia la plantilla del sistema ya que esta opcion
 * solo esta disponible en la plantilla antigua del sistema.
 * 
 * @param type $vendor el nombre del vendor
 * @param type $graphIds Ids de monitores a eliminar
 */
function deleteVendorMonitor($graphIds){
    
    /**
     * url de la aplicacion
     */
    
    $urlApp="http://localhost:8060";
        
    /**
     * Credenciales
     */
    $username='admin';
    $password='admin';
    $apiKey='37a71eee44cb4f74731a066484cf8628';
    
    /**
     * Monitores a eliminar
     */
    if(!is_array($graphIds)){
        $graphIds=array($graphIds);
    }
    
        
    /**
     * Agente para las peticiones
     */
    $userAgent="Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.134 Safari/537.36";
        
    /**
     * Parametros que enviamos a la pagina de logueo para simular inicio de sesion 
     */
    
    $paramsLogin="clienttype=html&isCookieADAuth=&domainName=NULL&authType=localUserLogin"
        . "&webstart=&ScreenWidth=1366&ScreenHeight=224"
        . "&loginFromCookieData=&userName=$username&password=$password&uname=&apiKey=$apiKey";

try {
        
        /**
         * Parametros a usar para iniciar la sesion por CURL
         */

        $curl_options[CURLOPT_RETURNTRANSFER] = TRUE;
        $curl_options[CURLOPT_USERAGENT] = $userAgent;        
        $curl_options[CURLOPT_URL] = "$urlApp/jsp/Login.do";                
        $curl_options[CURLOPT_POST] = 1;
        $curl_options[CURLOPT_FOLLOWLOCATION] = 1;
        $curl_options[CURLOPT_POSTFIELDS] = $paramsLogin; 
        
        $curl_options[CURLOPT_REFERER] = "$urlApp/LoginPage.do";
        $curl_options[CURLOPT_COOKIESESSION] = TRUE;
        $curl_options[CURLOPT_COOKIEJAR] =  'cookie-name';
        $curl_options[CURLOPT_COOKIEFILE] = dirname(__FILE__).'/cookie.tmp';
                       
 
        $curl = curl_init();
        curl_setopt_array($curl, $curl_options);  
        
        
        /**
         * Depurar la peticion realizada
         */
        
        $fp=fopen(dirname(__FILE__).'/curl.txt','w');                                           
        curl_setopt($curl,CURLOPT_VERBOSE,TRUE);
        curl_setopt($curl,CURLOPT_STDERR,$fp);


        curl_exec($curl);               
        
        
        if(curl_error($curl)){
            throw new Exception("Error al autenticar - " . curl_error());           
        }       
        
        /**
         * Setea la plantilla del sistema a la antigua para poder usar las pagina de eliminacion de monitores         
         */
        
        curl_setopt($curl,CURLOPT_URL,"$urlApp/api/json/admin/updateTheme?apiKey=$apiKey");
        curl_setopt($curl,CURLOPT_POST,TRUE);
        curl_setopt($curl,CURLOPT_POSTFIELDS,"apiKey=$apiKey&selectedSkin=CreamyBlue");
         
         
        curl_exec($curl); 
        
        
        
       if(curl_error($curl)){
           throw new Exception("Error al cambiar la plantilla - " . curl_error());       
       }
       
        curl_setopt($curl,CURLOPT_POST,FALSE);                
        
       
       /**
        * Eliminar los monitores deseados
        */
       foreach($graphIds as $graphId){
       
        curl_setopt($curl,CURLOPT_URL,"$urlApp/admin/AddDeviceTypeForm.do?operation=delete&graphid=$graphId");                        
       
         $resp=  curl_exec($curl); 
                
         if(curl_error($curl)){
          throw new Exception("Error al eliminar el monitor:$graphId de la plantilla:$templateId - " . curl_error());
        }         
        
       }
       
      // echo $resp;
   
       
       $result=true;
       $message='Monitores eliminados satisfactoriamente';
       curl_close($curl);
        
    } catch (Exception $exc) {
        $result=false;
        $message=$exc->getMessage();
        
    }
    
    return array('result'=>$result,'message'=>$message);
}

//$response=deleteVendorMonitor(array('1167','1168'));
//var_dump($response);