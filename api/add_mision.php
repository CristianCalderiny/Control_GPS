<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion/db.php';

try {
    $custodio_id   = $_POST['custodio_id']   ?? null;
    $tipo_mision   = $_POST['tipo_mision']   ?? null;
    $descripcion   = $_POST['descripcion']   ?? null;
    $observaciones = $_POST['observaciones'] ?? '';

    if (empty($custodio_id) || empty($tipo_mision) || empty($descripcion)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
        exit;
    }

    // Validar custodio
    $stmtCustodio = $conn->prepare("SELECT id FROM custodios WHERE id = ? AND estado = 'activo'");
    $stmtCustodio->execute([$custodio_id]);
    if ($stmtCustodio->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Custodio no válido o inactivo']);
        exit;
    }

    // Verificar que NO haya misión activa para este custodio
    $stmtVerificar = $conn->prepare("
        SELECT id FROM misiones 
        WHERE custodio_id = ? 
        AND estado IN ('posicionado', 'en_ruta', 'activa')
        AND fecha_fin IS NULL
        LIMIT 1
    ");
    $stmtVerificar->execute([$custodio_id]);
    if ($stmtVerificar->rowCount() > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Este custodio ya tiene una misión activa. Debe completarla antes de iniciar una nueva.'
        ]);
        exit;
    }

    // Obtener el primer GPS disponible
    $stmtGPS = $conn->prepare("SELECT id FROM gps_dispositivos WHERE estado = 'disponible' LIMIT 1");
    $stmtGPS->execute();
    $gpsRow = $stmtGPS->fetch(PDO::FETCH_ASSOC);
    if (!$gpsRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No hay GPS disponibles para asignar']);
        exit;
    }
    $gps_id = $gpsRow['id'];

    // ✅ CORREGIDO: descripcion y observaciones en columnas SEPARADAS
    $sql = "INSERT INTO misiones (
                custodio_id,
                gps_id,
                tipo_mision,
                descripcion,
                observaciones,
                fecha_inicio,
                estado
            ) VALUES (?, ?, ?, ?, ?, NOW(), 'pendiente')";

    $stmt = $conn->prepare($sql);
    $resultado = $stmt->execute([
        $custodio_id,
        $gps_id,
        $tipo_mision,
        $descripcion,          // ← solo la descripción
        $observaciones ?: null // ← solo las observaciones
    ]);

    if ($resultado) {
        $mision_id = $conn->lastInsertId();
        http_response_code(201);
        echo json_encode([
            'success'       => true,
            'message'       => 'Misión creada correctamente',
            'mision_id'     => $mision_id,
            'codigo_mision' => 'MIS-' . strtoupper($tipo_mision[0]) . '-' . $mision_id,
            'gps_id'        => $gps_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear la misión']);
    }

} catch (PDOException $e) {
    error_log("Error en add_mision.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
} catch (Exception $e) {
    error_log("Error general en add_mision.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>