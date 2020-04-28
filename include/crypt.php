<?php
function encrypt($data, $key)
{
    $iv = mcrypt_create_iv(16, MCRYPT_DEV_URANDOM);
	return base64_encode(
		$iv.
		mcrypt_encrypt(
			MCRYPT_RIJNDAEL_128,
			$key,
			json_encode($data),
			MCRYPT_MODE_CBC,
			$iv
		)
	);
}
function decrypt($data, $key)
{
    $decoded = base64_decode($data);
    $iv = mb_substr($decoded, 0, 16, '8bit');
    $ciphertext = mb_substr($decoded, 16, null, '8bit');
    
    $decrypted = rtrim(
        mcrypt_decrypt(
            MCRYPT_RIJNDAEL_128,
            $key,
            $ciphertext,
            MCRYPT_MODE_CBC,
            $iv
        ),
        "\0"
    );
    
    return json_decode($decrypted, true);
}?>