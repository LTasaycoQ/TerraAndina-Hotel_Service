<?php
date_default_timezone_set("America/Lima");
header("Content-Type: application/json");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$secretKey_cloudfare = $_ENV['SECRET_KEY_CLOUDFARE'] ?? null;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["ok" => false, "error" => "Método no permitido"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$nombre   = trim($data["nombres"] ?? "");
$apellido = trim($data["apellidos"] ?? "");
$email    = trim($data["email"] ?? "");
$telefono = trim($data["telefono"] ?? "");
$mensaje  = trim($data["mensaje"] ?? "");
$captcha  = $data["captcha"] ?? ""; 

if (!$nombre || !$apellido || !$email || !$telefono || !$mensaje || !$captcha) {
    echo json_encode(["ok" => false, "error" => "Faltan campos o verificación de seguridad"]);
    exit;
}

$url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

$verify = file_get_contents($url, false, stream_context_create([
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query([
            'secret'   => $secretKey_cloudfare,
            'response' => $captcha
        ])
    ]
]));

$responseData = json_decode($verify);

if (!$responseData || !$responseData->success) {
    echo json_encode(["ok" => false, "error" => "La verificación de seguridad falló."]);
    exit;
}

$nombre   = htmlspecialchars($nombre, ENT_QUOTES, "UTF-8");
$apellido = htmlspecialchars($apellido, ENT_QUOTES, "UTF-8");
$email    = filter_var($email, FILTER_SANITIZE_EMAIL);
$telefono = htmlspecialchars($telefono, ENT_QUOTES, "UTF-8");
$mensaje  = htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8");

function crearMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "noreply.terraandina@gmail.com";
    $mail->Password   = $_ENV['SECRET_EMAIL'] ?? ''; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->isHTML(false);
    return $mail;
}

try {
    $mail1 = crearMailer();
    $mail1->setFrom("noreply.terraandina@gmail.com", "Web Terra Andina");
    $mail1->addReplyTo($email, "$nombre $apellido");
    $mail1->addAddress("luistasayco3030@gmail.com");

    $mail1->Subject = "Consulta WEB - Terra Andina Mansion Colonial (" . date("H:i:s") . ")";
    $mail1->Body =
        "Nueva Consulta - WEB Terra Andina Mansion Colonial:\n\n" .
        "Nombre: $nombre\n" .
        "Apellido: $apellido\n" .
        "Telefono: $telefono\n" .
        "Email: $email\n\n" .
        "Mensaje:\n$mensaje\n";

    $mail1->send();

    $mail2 = crearMailer();
    $mail2->setFrom("noreply.terraandina@gmail.com", "Terra Andina Hotel");
    $mail2->addAddress($email, $nombre);

    $mail2->Subject = "Hemos recibido tu consulta";
    $mail2->Body =
        "Hola $nombre,\n\n" .
        "Hemos recibido tu mensaje correctamente. Esto es lo que nos enviaste:\n\n" .
        "Mensaje:\n$mensaje\n\n" .
        "Pronto nos pondremos en contacto contigo.\n\n" .
        "— Terra Andina Hotel";

    $mail2->send();

    echo json_encode([
        "ok" => true,
        "message" => "Mensaje enviado con éxito"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "ok" => false,
        "error" => "No se pudo enviar el correo",
        "detail" => $e->getMessage()
    ]);
}