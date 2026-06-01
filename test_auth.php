<?php
$ch = curl_init('http://localhost/barberia/sistema/auth.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['usuario' => 'carlo', 'password' => '123']));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
$result = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
echo "HTTP " . $info['http_code'] . "\n";
echo "Response: " . $result . "\n";
?>
