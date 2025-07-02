<?php

// Obtener el directorio padre del archivo actual
echo "...Generando Mensaje JSON... ";

$datos = array(
"cod_linea" => "L07",
"id_tren" => "TL07101",
"id_evento" => "9",
"desc_evento"  => "Sensoritox"
);

$json_datos = json_encode($datos);
$ruta_archivo = 'C:\\xampp\\htdocs\\ws\\com\\endpoint\\datos.json';

// Verificar errores antes de escribir en el archivo
if (json_last_error() === JSON_ERROR_NONE) 
{
  file_put_contents($ruta_archivo, $json_datos);
     echo "<br>";
     echo "...Mensaje JSON generado correctamente...";
} else 
{
     echo "Error al convertir a JSON: " . json_last_error_msg();
}
?>