<?php
	require '.././libs/autoload.php';
	$app = new \Slim\App();//['settings' => ['displayErrorDetails' => true]]
		
	$app->post('/login', function($request, $response, $args) {
		
		$conn=connect_db();
		$contact_no=$_POST["contact_no"];
		$contact_no=addslashes($contact_no);
		$password=$_POST["password"];
		$password=addslashes($password);
		$password=md5(md5($password));
		
		$mysql_qry="SELECT * FROM user_info WHERE contact_no='$contact_no' AND password='$password'";
		$result=mysqli_query($conn,$mysql_qry);
			
		date_default_timezone_set('Asia/Calcutta');
		$date = date('D/m/y H:i:s');

		if(mysqli_num_rows($result)>0){
			$row=mysqli_fetch_assoc($result);
			$name=$row["name"];
			
			$auth_code=updateAuthCode($contact_no);
			
			$mysql_qry="INSERT INTO login_log (contact_no,date_time,status,comment) VALUES ('$contact_no','$date','true','user_name & user_pass matched')";			
			$result=mysqli_query($conn,$mysql_qry);
			
			$output=array(
					"authorization"=>$auth_code,
					"name"=>$name,
					"status"=>"701"
			);
			$response->write(json_encode($output));
			mysqli_close($conn);
			return $response;	
		}
		$mysql_qry="INSERT INTO login_log (contact_no,date_time,status,comment) VALUES ('$contact_no','$date','false','Incorrect username or password')";			
		$result=mysqli_query($conn,$mysql_qry);
		
		$output=array(
				"status"=>"702"
		);
		$response->write(json_encode($output));
		mysqli_close($conn);
		return $response;	
	});

	$app->post('/register', function($request, $response, $args) {
		$contact_no=$_POST["contact_no"];
		$contact_no=addslashes($contact_no);

		$name=$_POST["name"];
		$name=addslashes($name);

		$password=$_POST["password"];	
		$password=addslashes($password);
		$password=md5(md5($password));

		$sec_question=$_POST["sec_question"];
		$sec_question=addslashes($sec_question);

		$sec_answer=$_POST["sec_answer"];
		$sec_answer=addslashes($sec_answer);
		
		$auth_code=generateApiKey();
		$conn=connect_db();
		
		$create_user="CALL insert_all('$contact_no', '$name', '$password', '$sec_question', '$sec_answer', '$auth_code')";
		$proc_result=mysqli_query($conn,$create_user);

		$tbl_name="data_".$contact_no;
		$create_table="CREATE TABLE $tbl_name(contact_no VARCHAR(13),message TEXT,timestamp INT(20))";
		$tbl_result=mysqli_query($conn,$create_table);

		echo "PROC_RESULT".$proc_result;
		echo "TBL_RESULT".$tbl_result;
		if($proc_result){
			if($tbl_result){
					$output=array(
					"authorization"=>$auth_code,
					"status"=>"701"
				);
				$response->write(json_encode($output));			
				mysqli_close($conn);
				return $response;
			}
		}else{
			$output=array(
					"status"=>"702"
			);
			$response->write(json_encode($output));			
			mysqli_close($conn);
			return $response;
		}
	});

	$app->post('/recover', function($request, $response, $args) {
		$auth_code=$_POST["authorization"];
		$auth_code=addslashes($auth_code);
		
		$sec_question=$_POST["sec_question"];
		$sec_question=addslashes($sec_question);

		$sec_answer=$_POST["sec_answer"];
		$sec_answer=addslashes($sec_answer);
		$conn=connect_db();
		
		$mysql_qry="SELECT * FROM user_join WHERE auth_code='$auth_code' AND sec_question='$sec_question' AND sec_answer='$sec_answer'";
		$result=mysqli_query($conn,$mysql_qry);

		if($row=mysqli_fetch_assoc($result)){
			$password=$_POST["password"];
			$password=addslashes($password);
			$password=md5(md5($password));
			$mysql_qry="UPDATE TABLE user_join SET password='$password' WHERE auth_code='$auth_code'";
			$result=mysqli_query($conn,$mysql_qry);
			if($result){
				$output=array(
						"authorization"=>$auth_code,
						"status"=>"701"
				);
				$response->write(json_encode($output));			
				return $response;
			}
		}
		$output=array(
				"status"=>"702"
		);
		$response->write(json_encode($output));			
		mysqli_close($conn);
		return $response;
	});

	$app->post('/send', function($request, $response, $args) {
		$conn=connect_db();
		$time = time();
		$target_contact_no=$_POST["contact_no"];
		$auth_code=$_POST["authorization"];
		$message=$_POST["message"];
		$mysql_qry=	"SELECT * FROM user_join where auth_code='$auth_code'";
		$result=mysqli_query($conn,$mysql_qry);
		if($row=mysqli_fetch_assoc($result)){
			$contact_no=$row["contact_no"];
			$tbl_name="data_".$target_contact_no;
			$mysql_qry=	"INSERT INTO $tbl_name VALUES('$contact_no','$message',$time)";		
			$result=mysqli_query($conn,$mysql_qry);
			if($result){
				$output=array(
						"status"=>"701"
				);
				$response->write(json_encode($output));			
				mysqli_close($conn);
				return $response;
			}
			else{
				$output=array(
						"status"=>"702"
				);
				$response->write(json_encode($output));			
				mysqli_close($conn);
				return $response;
			}
		}
	});

	$app->post('/receive', function($request, $response, $args) { 
		$target_contact_no=$_POST["contact_no"];
		$auth_code=$_POST["authorization"];
		$timestamp=intval($_POST["timestamp"]);
		$curr_time=time();
		$conn=connect_db();
		$mysql_qry=	"SELECT * FROM user_join where auth_code='$auth_code'";
		$result=mysqli_query($conn,$mysql_qry);
		if($row=mysqli_fetch_assoc($result)){
			$contact_no=$row["contact_no"];
			$tbl_name="data_".$contact_no;
			$mysql_qry=	"SELECT * FROM $tbl_name WHERE contact_no='$target_contact_no' AND timestamp > $timestamp";
			$result=mysqli_query($conn,$mysql_qry);
			if($result){
				$output=array(
						"status"=>"701",
						"timestamp"=>$curr_time
				);

				$count=0;
				while($row=mysqli_fetch_array($result)){
					$msg[$count]=array(
								"contact_no"=>$row["contact_no"],
								"message"=>$row["message"],
								"timestamp"=>$row["timestamp"]
							);
					$count++;
				}	
				$output+=$msg;
				$response->write(json_encode($output));			
			}else{
				$output="{status:702}";
				$response->write($output);
			}
			mysqli_close($conn);
			return $response;
		}
	});

	$app->post('/validate', function($request, $response, $args) {
		
		$conn=connect_db();
		$contact_no=$_POST["contact_no"];
		$contact_no=addslashes($contact_no);

		$mysql_qry="SELECT * FROM user_info WHERE contact_no='$contact_no'";
		$result=mysqli_query($conn,$mysql_qry);

		if(mysqli_num_rows($result)>0){
			$row=mysqli_fetch_assoc($result);
			$name=$row["name"];

			$output=array(
					"name"=>$name,
					"status"=>"701"
			);
			$response->write(json_encode($output));
			mysqli_close($conn);
			return $response;	
		}
		$output=array(
				"status"=>"702"
		);
		$response->write(json_encode($output));
		mysqli_close($conn);
		return $response;	
	});

	
	function generateApiKey() {
		return md5(uniqid(rand(), true));
    }
	
	function updateAuthCode($contact_no){
		$conn=connect_db();
		$auth_code=generateApiKey();
		$mysql_qry="UPDATE user_auth SET auth_code='$auth_code' WHERE contact_no='$contact_no'";			
		$result=mysqli_query($conn,$mysql_qry);
		mysqli_close($conn);
		if($result){
			return $auth_code;
		}
		return null;		
	}
	function connect_db() {
		$server = 'localhost'; 
		$user = 'dbuser';
		$pass = 'ec2@DBUser';
		$database = 'secure_channel'; 

		/*
		$server = 'localhost'; 
		$user = 'profusio_secure';
		$pass = '5Qr$~Bu(_p!#[;Q@l%';
		$database = 'profuusio_secure_channel'; 
		*/
		$connection = new mysqli($server, $user, $pass, $database);
	 
		return $connection;
	}
	
	function encrypt($plaintext){
		$key = pack('H*', "bcb04b7e103a0cd8b54763051cef08bc55abe029fdebae5e1d417e2ffb2a00a3");
		
		$bytes = random_bytes(50);
		echo uniqid(var_dump(bin2hex($bytes)));
    
		$key_size =  strlen($key);
		echo "Key size: " . $key_size . "\n";
		
		$plaintext = "This string was AES-256 / CBC / ZeroBytePadding encrypted.";

		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		
		$ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key,
									 $plaintext, MCRYPT_MODE_CBC, $iv);
		$ciphertext = $iv . $ciphertext;

		$ciphertext_base64 = base64_encode($ciphertext);

		echo  $ciphertext_base64 . "\n";

	}
	function decrypt($ciphertext_base64,$iv_size){
		$ciphertext_dec = base64_decode($ciphertext_base64);
		
		$iv_dec = substr($ciphertext_dec, 0, $iv_size);
		
		$ciphertext_dec = substr($ciphertext_dec, $iv_size);

		$plaintext_dec = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key,
										$ciphertext_dec, MCRYPT_MODE_CBC, $iv_dec);
		
		echo  $plaintext_dec . "\n";
	}
	$app->run();
?>
