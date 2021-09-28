<?php
	

	##Classe de Token de sua Preferencia
	include_once('../token/Token.php');


	function debug(){
		ini_set('display_errors', '1');
		ini_set('display_startup_errors', '1');
		error_reporting(E_ALL);
	}
	// debug();
	function is_base64($s) { 
		return (bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s); 
	} 
	
	require 'config.php';

	require 'PHPMailer-master/src/Exception.php';
	require 'PHPMailer-master/src/PHPMailer.php';
	require 'PHPMailer-master/src/SMTP.php';

	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;

	header("Content-Type: application/json");
	header("Access-Control-Allow-Origin: *");
	header('Access-Control-Allow-Headers: *');

	$body = file_get_contents('php://input');

	try {
		switch($_SERVER['REQUEST_METHOD']){
			case 'POST':
			
				$body = json_decode($body);
				if(!isset($body->token)){
					
					$httpCode = 401;
					$return = array("status" => 401, "message" => "Faltando Token!");
					// var_dump($body);
				}else{
					$TokenVip = new Token($body->token, 1);
					$auth = $TokenVip->authToken();
					
					if($auth['status'] != 200){
						
						$httpCode = 401;
						$return = array("status" => 401, "message" => "Token Invalido!");

					}else{


						######################### V A L I D A R   Q U A T I D A D E #################
						$centroCusto = $TokenVip->verificaCentroCusto();
						if($centroCusto['status'] == 200){
							$free = true;
						}else{
							if($centroCusto['free'] > 0){
								$free = true;
							}else{
								$free = false;
							}
						}


						if($free){

							########################
							if(!isset($body->destino)){
								$httpCode = 200;
								$return = array("status" => 204, "message" => "Faltando destino");
							}else if(!isset($body->assunto)){
								$httpCode = 200;
								$return = array("status" => 204, "message" => "Faltando assunto");
							}else if(!isset($body->mensagem)){
								$httpCode = 200;
								$return = array("status" => 204, "message" => "Faltando mensagem");
							}






							$mail = new PHPMailer();


							if(!filter_var($body->destino, FILTER_VALIDATE_EMAIL)){
								### EMAIL INVALIDO
								$httpCode = 200;
								$return = array("status" => 204, "message" => "Destino invalido");
							} else {
								$anexos = true;
								$anexos_array = true;
								if(isset($body->anexo)){
									if(is_array($body->anexo)){
										$tt = count($body->anexo);
										for($i = 0; $i < $tt ; $i++){
											if( (!is_base64($body->anexo[$i]->content)) || (empty($body->anexo[$i]->content)) )
												$anexos = false;
										}

									}else{
										$anexos_array = false;
									}
								}


								if(!$anexos){
									$httpCode = 200;
									$return = array("status" => 204, "message" => "contem anexo que não está em base64");
								}else if(!$anexos_array){
									$httpCode = 200;
									$return = array("status" => 204, "message" => "Formato do anexo invalido");
								}else{

									$mail->AddAddress($body->destino);
									$mail->IsSMTP();
									$mail->Host = CONF_MAIL_HOST;
									$mail->SMTPAuth = true;
									$mail->SMTPSecure = "tls";
									$mail->Port = CONF_MAIL_PORT;
									$mail->Username = CONF_MAIL_USERNAME;
									$mail->Password = CONF_MAIL_PASSWD;
									$mail->From = CONF_MAIL_USERNAME;
									$mail->FromName = CONF_MAIL_NAME;
									$mail->IsHTML(true);
									$mail->CharSet = "UTF-8";

									$erro_array = false;
									##### anexo
									if(isset($body->anexo)){
										if(!is_array($body->anexo)){
											$erro_array = true;
										}else{
											$tt = count($body->anexo);
											for($i = 0; $i < $tt ; $i++){
												#### CRIA ARQUIVOS TMP PARA ANEXO
												$temp[$i] = tmpfile();
												fwrite($temp[$i], base64_decode($body->anexo[$i]->content));
												fseek($temp[$i], 0);
												$path = stream_get_meta_data($temp[$i])['uri']; 
												################################
												$mail->AddAttachment($path, $body->anexo[$i]->nome);
												
											}
										}
									}


									#####
									if($erro_array){
										$httpCode = 200;
										$return = array("status" => 204, "message" => "Anexo não está em array");
									}else{

										$mail->Subject  = $body->assunto;
										$mail->Body = $body->mensagem;
										$mail->AltBody = trim(strip_tags($body->mensagem));

										if ($mail->Send()) {
											$httpCode = 200;
											$return = array("status" => 200, "message" => "Email enviado com sucesso!");
											$TokenVip->storeRecord("Email enviado para: ".$body->destino);


										} else {
											$httpCode = 200;
											
											$TokenVip->storeRecord("Erro ao enviar email: ".$mail->ErrorInfo);
											$return = array("status" => 204, "message" => "Ocorreu um erro ao enviar email!");
										}

										##### DELETA ARQUIVOS TEMPORARIOS
										if(isset($body->anexo)){
											if(is_array($body->anexo)){
												$tt = count($body->anexo);
												for($i = 0; $i < $tt ; $i++){
													fclose($temp[$i]);
												}
											}
										}
										#########################################
									}
							

								}

							}

						}else{
							$httpCode = 200;
							$return = array("status" => 208, "message" => "Limite do mês atingido!");
						}
						####################
					}

				}
			break;
			default:
				$httpCode = 200;
				$return = array("status" => 204, "message" => "Método ".$_SERVER['REQUEST_METHOD']." invalido.");
			break;
		}

		print_r(json_encode($return));
		http_response_code($httpCode);

	} catch (Exception $e) {
		
		http_response_code(500);

	}