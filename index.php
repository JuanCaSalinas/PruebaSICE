<?php
/** Web Service MRO-COM en PHP con MySQL */
////require 'php-json-file-decode-master/json-file-decode.class.php';

// Configura el tipo de contenido a JSON
header("Content-Type: application/json");
$fecha_actual = date("Y-m-d H:i:s"); // Fecha y hora actual del servidor 	

// Conexión a la base de datos
$host = "localhost";
$db = "alarmas_ihm";
$user = "root";
$pass = "";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión: " . $mysqli->connect_error]);
    exit;
}

// Leer y decodificar el JSON recibido
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["error" => "JSON inválido"]);
	$mysqli->close();
    exit;
}

// Validar que se recibieron los campos esperados
$required_fields = [
    "fecha_ocurrencia", "fecha_evento", "id_tren", "coche",
    "codigo_dispositivo", "valor_dispositivo", "severidad", "mensaje"
];

foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el campo requerido: $field"]);
		$mysqli->close();
        exit;
    }
}

//echo " ... Preparar datos para inserción ..... ";
//echo "<br>";

// Preparar los datos para inserción
$fecha_ocurrencia = $mysqli->real_escape_string($data["fecha_ocurrencia"]);
$fecha_evento = $mysqli->real_escape_string($data["fecha_evento"]);
$id_tren = (int) $data["id_tren"];
$coche = (int) $data["coche"];
$codigo_dispositivo = $mysqli->real_escape_string($data["codigo_dispositivo"]);
$valor_dispositivo = $mysqli->real_escape_string($data["valor_dispositivo"]);
$severidad = $mysqli->real_escape_string($data["severidad"]);
$mensaje = $mysqli->real_escape_string($data["mensaje"]);
	
// Obtener severidad del cuerpo recibido
$severidad = $data['severidad'] ?? null;
// Validar severidad
$valoresPermitidos = ['C', 'M', 'N'];
if (!in_array($severidad, $valoresPermitidos)) {
    http_response_code(400);
	    
	echo json_encode([
    "error" => "Atributo 'severidad' inválido. Alarma no procesada.",
    "valor_recibido" => $severidad,
    "fecha_proceso" => $fecha_actual
	]);
	
	$mysqli->close();
    exit;	
}

// valor del dispositivo solo corresponde a 0: Desactivado 1: Activado
$valoresPermitidos = [0,1];
if (!in_array($valor_dispositivo, $valoresPermitidos)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Atributo 'valor_dispositivo' inválido. Alarma no procesada. ",
        "valor_recibido" => $valor_dispositivo,
		"fecha_proceso" => $fecha_actual
    ]);
	$mysqli->close();
    exit;
}

// valida que los coches solo puedan tener valor del 1 al 5
$valoresPermitidos = [1,2,3,4,5];
if (!in_array($coche, $valoresPermitidos)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Atributo 'coche' inválido. Alarma no procesada. ",
        "valor_recibido" => $coche,
		"fecha_proceso" => $fecha_actual
    ]);
	$mysqli->close();
    exit;
}

// Consulta para verificar existencia de Código de Dispositivo en tabla de Dispositivos
$sql = "SELECT nombre FROM dispositivos WHERE codigo_dispositivo = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $codigo_dispositivo);
$stmt->execute();
$resultado = $stmt->get_result();

// Validar si NO existe el valor
if ($resultado->num_rows === 0) 
{
    //echo "El Código de Dispositivo '$variable1' NO existe en la lista de señales.\n";
	http_response_code(400);
    echo json_encode([
        "error" => "..Codigo de Dispositivo NO existe en la lista de senales. Alarma no procesada. ",
        "..valor_recibido:" => $codigo_dispositivo,
		"fecha_proceso" => $fecha_actual
    ]);
	$mysqli->close();
    exit;
} 

/* Consulta para obtener el ultimo Valor del Dispositivo si es que existe en la tabla 
   y Validar que el valor enviado no sea el ultimo obtenido */
 
$sql = " SELECT valor_dispositivo, fecha_recepcion FROM alarmas_mro WHERE id_tren=? and codigo_dispositivo = ?";
$sql = $sql . " ORDER BY fecha_recepcion DESC LIMIT 1 ";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("is", $id_tren, $codigo_dispositivo);
$stmt->execute();
$resultado = $stmt->get_result();

// Validar si NO existe el valor
if ($resultado->num_rows > 0) 
{
    if ($resultado && $fila = $resultado->fetch_assoc()) 
	{
        $valor = $fila['valor_dispositivo'];	
		if ($valor == $valor_dispositivo )
		{
			//echo "El Valor del Dispositivo asociado ya esta con el valor del dispositivo ";
			http_response_code(400);
			echo json_encode([
            "error" => "..Valor del Dispositivo asociado, actualmente ya se encuentra con el estado enviado. Alarma no procesada.",
            "valor_recibido" => $valor_dispositivo,
			"fecha_proceso" => $fecha_actual
            ]);
			
			$mysqli->close();
			exit;				
		}			
	}
} 

// Insertar en la base de datos
$query = "
    INSERT INTO alarmas_mro (
        fecha_ocurrencia, fecha_envio_evento, id_tren, coche,
        codigo_dispositivo, valor_dispositivo, severidad, mensaje
    ) VALUES (
        '$fecha_ocurrencia', '$fecha_evento', $id_tren, $coche,
        '$codigo_dispositivo', '$valor_dispositivo', '$severidad', '$mensaje'
    )
";

if ($mysqli->query($query)) 
{
    http_response_code(200);
    //echo json_encode(["success" => true]);
	echo " OK.";
} else 
{
    http_response_code(500);
    echo json_encode(["error" => "Error de conexion a la base de datos: " . $mysqli->error]);
	$mysqli->close();
}
$mysqli->close();  
?>
