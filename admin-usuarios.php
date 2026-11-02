<?php
require_once __DIR__ . '/includes/sesion.php';
requiereLogin();
requiereAdmin();
$yo = usuarioActual();

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $rol = ($_POST['rol'] ?? 'estandar') === 'admin' ? 'admin' : 'estandar';
    $password = $_POST['password'] ?? '';

    if ($nombre === '' || $usuario === '' || $password === '') {
        $error = 'Nombre, usuario y contraseña son obligatorios.';
    } elseif (preg_match('/\s/u', $usuario)) {
        $error = 'El usuario para iniciar sesión no puede llevar espacios (usa por ejemplo juan.perez).';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conexion, "INSERT INTO usuarios (nombre, usuario, correo, area, password_hash, rol) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssss", $nombre, $usuario, $correo, $area, $hash, $rol);
        if (mysqli_stmt_execute($stmt)) {
            registrarBitacora($conexion, 'Creó cuenta de usuario', "$nombre ($rol)");
            $exito = "Cuenta creada para $nombre.";
        } else {
            $error = 'No se pudo crear la cuenta (revisa que el usuario no exista ya).';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'estado') {
    $id = (int) $_POST['id'];
    if ($id === (int) $yo['id']) {
        $error = 'No puedes desactivar tu propia cuenta: te quedarías sin poder entrar.';
    } else {
        $stmtN = mysqli_prepare($conexion, "SELECT nombre FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($stmtN, "i", $id);
        mysqli_stmt_execute($stmtN);
        $nombreU = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN)) ?: [])['nombre'] ?? "id $id";
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET activo = 1 - activo WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Cambió el estado de una cuenta', $nombreU);
    }
}

// Restablecer contraseña (por si un usuario estándar la pierde/olvida)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'restablecer') {
    $id = (int) $_POST['id'];
    $nueva = $_POST['nueva_password'] ?? '';
    if (strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } else {
        $stmtN = mysqli_prepare($conexion, "SELECT nombre FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($stmtN, "i", $id);
        mysqli_stmt_execute($stmtN);
        $nombreU = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN)) ?: [])['nombre'] ?? "id $id";
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET password_hash = ?, debe_cambiar_password = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Restableció contraseña', $nombreU);
        $exito = 'Contraseña restablecida. Avísale a la persona su nueva contraseña — la próxima vez que entre, el sistema le va a pedir que la cambie por una propia.';
    }
}

// Cambiar el área de una cuenta existente (por si alguien cambia de unidad)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_area') {
    $id = (int) $_POST['id'];
    $nuevaArea = trim($_POST['nueva_area'] ?? '');
    $stmtN = mysqli_prepare($conexion, "SELECT nombre FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmtN, "i", $id);
    mysqli_stmt_execute($stmtN);
    $nombreU = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN)) ?: [])['nombre'] ?? "id $id";
    $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET area = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $nuevaArea, $id);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, 'Cambió el área de un usuario', "$nombreU → $nuevaArea");
    $exito = "Área actualizada para $nombreU.";
}

// Cambiar el rol de otra cuenta: exige la contraseña del administrador que lo hace.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_rol') {
    $id = (int) $_POST['id'];
    $nuevoRol = ($_POST['nuevo_rol'] ?? '') === 'admin' ? 'admin' : 'estandar';
    $passwordAdmin = $_POST['password_admin'] ?? '';

    $stmtA = mysqli_prepare($conexion, "SELECT nombre, rol FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmtA, "i", $id);
    mysqli_stmt_execute($stmtA);
    $objetivo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));

    $stmtH = mysqli_prepare($conexion, "SELECT password_hash FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmtH, "i", $yo['id']);
    mysqli_stmt_execute($stmtH);
    $miHash = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtH)) ?: [])['password_hash'] ?? '';

    if ($id === (int) $yo['id']) {
        $error = 'No puedes cambiar tu propio rol: podrías quedarte sin acceso de administrador.';
    } elseif (!$objetivo) {
        $error = 'No se encontró esa cuenta.';
    } elseif ($passwordAdmin === '' || !password_verify($passwordAdmin, $miHash)) {
        registrarBitacora($conexion, 'Intento fallido de cambio de rol (contraseña incorrecta)', $objetivo['nombre']);
        $error = 'Tu contraseña de administrador no es correcta, el rol no se cambió.';
    } elseif ($objetivo['rol'] === $nuevoRol) {
        $error = $objetivo['nombre'] . ' ya tiene ese rol.';
    } else {
        $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET rol = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $nuevoRol, $id);
        mysqli_stmt_execute($stmt);
        registrarBitacora($conexion, 'Cambió el rol de una cuenta', $objetivo['nombre'] . ': ' . $objetivo['rol'] . ' → ' . $nuevoRol);
        $exito = $objetivo['nombre'] . ' ahora es ' . ($nuevoRol === 'admin' ? 'Administrador' : 'Usuario estándar') . '. El cambio le aplica de inmediato.';
    }
}

// Corregir los datos de una cuenta (nombre, usuario de acceso, correo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_perfil') {
    $id = (int) $_POST['id'];
    $nuevoNombre = trim($_POST['nombre'] ?? '');
    $nuevoUsuario = trim($_POST['usuario'] ?? '');
    $nuevoCorreo = trim($_POST['correo'] ?? '');

    $stmtA = mysqli_prepare($conexion, "SELECT nombre, usuario, correo FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmtA, "i", $id);
    mysqli_stmt_execute($stmtA);
    $antes = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));

    if (!$antes) {
        $error = 'No se encontró esa cuenta.';
    } elseif ($nuevoNombre === '' || $nuevoUsuario === '') {
        $error = 'El nombre y el usuario no pueden quedar vacíos.';
    } elseif ($nuevoUsuario !== $antes['usuario'] && preg_match('/\s/u', $nuevoUsuario)) {
        $error = 'El usuario para iniciar sesión no puede llevar espacios (usa por ejemplo juan.perez).';
    } elseif (mb_strlen($nuevoUsuario) > 50) {
        $error = 'El usuario es demasiado largo (máximo 50 caracteres).';
    } else {
        $stmtD = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE usuario = ? AND id <> ?");
        mysqli_stmt_bind_param($stmtD, "si", $nuevoUsuario, $id);
        mysqli_stmt_execute($stmtD);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtD))) {
            $error = "El usuario \"$nuevoUsuario\" ya lo tiene otra cuenta, elige uno distinto.";
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET nombre = ?, usuario = ?, correo = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $nuevoNombre, $nuevoUsuario, $nuevoCorreo, $id);
            mysqli_stmt_execute($stmt);

            $cambios = [];
            if ($antes['nombre'] !== $nuevoNombre) $cambios[] = "nombre: {$antes['nombre']} → $nuevoNombre";
            if ($antes['usuario'] !== $nuevoUsuario) $cambios[] = "usuario: {$antes['usuario']} → $nuevoUsuario";
            if (($antes['correo'] ?? '') !== $nuevoCorreo) $cambios[] = 'correo';
            if ($cambios) {
                registrarBitacora($conexion, 'Editó los datos de una cuenta', implode('; ', $cambios));
            }

            // Si se editó la cuenta con la que se tiene la sesión abierta, se refleja de inmediato.
            if ($id === (int) $yo['id']) {
                $_SESSION['usuario_nombre'] = $nuevoNombre;
                $_SESSION['usuario_login'] = $nuevoUsuario;
                $yo = usuarioActual();
            }
            $exito = 'Datos actualizados para ' . $nuevoNombre . ($antes['usuario'] !== $nuevoUsuario ? '. Avísale que su nuevo usuario para iniciar sesión es: ' . $nuevoUsuario : '.');
        }
    }
}

// Activar/desactivar y editar el botón general de "Mi Nextcloud" (un solo
// enlace para todo el personal, no por usuario).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_nextcloud') {
    $urlNextcloud = trim($_POST['url_nextcloud'] ?? '') ?: 'https://nube.ejemplo.com';
    $activoNextcloud = !empty($_POST['activo']) ? 1 : 0;
    $stmt = mysqli_prepare($conexion, "UPDATE nextcloud_config SET activo = ?, url = ?, actualizado_por = ? WHERE id = 1");
    mysqli_stmt_bind_param($stmt, "isi", $activoNextcloud, $urlNextcloud, $yo['id']);
    mysqli_stmt_execute($stmt);
    registrarBitacora($conexion, $activoNextcloud ? 'Activó el botón de Nextcloud' : 'Desactivó el botón de Nextcloud');
    $exito = $activoNextcloud ? 'El botón "Nextcloud" ya es visible para todo el personal.' : 'Se desactivó el botón "Nextcloud".';
}

// Borra las notas personales de calendario de un usuario, sin tocar el resto
// de su cuenta (por ejemplo si cambió de puesto o quieres limpiar su agenda).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'borrar_notas_calendario') {
    $id = (int) $_POST['id'];
    $stmtN = mysqli_prepare($conexion, "SELECT nombre FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmtN, "i", $id);
    mysqli_stmt_execute($stmtN);
    $nombreU = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN)) ?: [])['nombre'] ?? "id $id";
    $stmt = mysqli_prepare($conexion, "DELETE FROM calendario_notas_personales WHERE usuario_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $borrados = mysqli_stmt_affected_rows($stmt);
    registrarBitacora($conexion, 'Borró las notas personales de calendario de un usuario', $nombreU);
    $exito = "Se borraron $borrados nota(s) personal(es) de calendario de $nombreU.";
}

// Eliminar cuenta (solo si no tiene tickets/comunicados/etc. registrados a su nombre)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = (int) $_POST['id'];
    if ($id === (int) $yo['id']) {
        $error = 'No puedes eliminar tu propia cuenta mientras tienes la sesión abierta.';
    } else {
        $stmtN = mysqli_prepare($conexion, "SELECT nombre FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($stmtN, "i", $id);
        mysqli_stmt_execute($stmtN);
        $nombreU = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN)) ?: [])['nombre'] ?? "id $id";
        // Las tablas de "activo/publicado por" de una sola fila solo guardan quién hizo el último cambio, no historial: se sueltan para que no impidan eliminar.
        foreach (['aviso_reunion', 'pagina_extra', 'popup_aviso', 'nextcloud_config', 'encuesta_config'] as $tablaConfig) {
            mysqli_query($conexion, "UPDATE `$tablaConfig` SET actualizado_por = NULL WHERE actualizado_por = $id");
        }
        $stmt = mysqli_prepare($conexion, "DELETE FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            registrarBitacora($conexion, 'Eliminó cuenta de usuario', $nombreU);
            $exito = 'Cuenta eliminada.';
        } else {
            // Se busca exactamente qué registros a nombre de esta persona lo impiden, para decirlo con claridad.
            $etiquetas = [
                'tickets.usuario_id' => ['ticket creado', 'tickets creados'],
                'tickets.atendido_por' => ['ticket atendido', 'tickets atendidos'],
                'ticket_participantes.usuario_id' => ['ticket compartido', 'tickets compartidos'],
                'comunicados.publicado_por' => ['comunicado publicado', 'comunicados publicados'],
                'cursos.publicado_por' => ['curso publicado', 'cursos publicados'],
                'tutoriales.publicado_por' => ['tutorial publicado', 'tutoriales publicados'],
                'eventos_programados.publicado_por' => ['evento publicado', 'eventos publicados'],
                'directorio_contactos.publicado_por' => ['contacto del directorio', 'contactos del directorio'],
                'cumpleanos.creado_por' => ['cumpleaños registrado', 'cumpleaños registrados'],
                'calendario_eventos.creado_por' => ['día del calendario', 'días del calendario'],
            ];
            $motivos = [];
            $resFk = mysqli_query($conexion, "SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c
                FROM information_schema.KEY_COLUMN_USAGE k
                JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                WHERE k.REFERENCED_TABLE_NAME = 'usuarios' AND k.TABLE_SCHEMA = DATABASE() AND r.DELETE_RULE IN ('RESTRICT', 'NO ACTION')");
            while ($resFk && ($fk = mysqli_fetch_assoc($resFk))) {
                $cuenta = mysqli_query($conexion, "SELECT COUNT(*) AS n FROM `{$fk['t']}` WHERE `{$fk['c']}` = $id");
                $n = $cuenta ? (int) mysqli_fetch_assoc($cuenta)['n'] : 0;
                if ($n > 0) {
                    $par = $etiquetas[$fk['t'] . '.' . $fk['c']] ?? [$fk['t'], $fk['t']];
                    $motivos[] = $n . ' ' . ($n === 1 ? $par[0] : $par[1]);
                }
            }
            $lista = $motivos ? implode(', ', $motivos) : 'registros en el sistema';
            $error = "No se puede eliminar a $nombreU porque tiene a su nombre: $lista. Qué hacer: usa \"Desactivar\" (ya no podrá entrar y conservas el historial), o si de verdad quieres eliminarla, primero borra ese contenido desde su sección y vuelve a intentar.";
        }
    }
}

$usuarios = [];
$resultado = mysqli_query($conexion, "SELECT id, nombre, usuario, correo, area, rol, activo FROM usuarios ORDER BY nombre");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $usuarios[] = $fila;
}

$areas = obtenerAreas($conexion);

$nextcloud = ['activo' => 0, 'url' => 'https://nube.ejemplo.com'];
$resultadoNc = mysqli_query($conexion, "SELECT activo, url FROM nextcloud_config WHERE id = 1");
if ($resultadoNc) {
    $filaNc = mysqli_fetch_assoc($resultadoNc);
    if ($filaNc) $nextcloud = $filaNc;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SAASCAEM - Usuarios</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=31">
    <link rel="stylesheet" href="tema.php">
</head>
<body>

<div id="nav-placeholder"></div>

<div class="main-content">
    <h1>👤 Usuarios</h1>
    <p><a href="admin-panel.php" class="enlace-volver">← Panel de Administración</a></p>

    <?php if ($error): ?>
        <div class="login-error" style="display:block; max-width:600px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="aviso-importante" style="max-width:600px;"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <h2>Crear cuenta nueva</h2>
    <form method="post" style="max-width:560px; background:var(--card-bg); padding:24px; border-radius:16px; box-shadow:var(--shadow); margin-bottom:40px;">
        <input type="hidden" name="accion" value="crear">
        <div class="campo">
            <label for="nombre">Nombre completo</label>
            <input id="nombre" name="nombre" type="text" required>
        </div>
        <div class="campo">
            <label for="usuario">Usuario para iniciar sesión</label>
            <input id="usuario" name="usuario" type="text" required maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')" placeholder="Sin espacios, ej. juan.perez">
        </div>
        <div class="campo">
            <label for="correo">Correo (opcional)</label>
            <input id="correo" name="correo" type="email">
        </div>
        <div class="campo">
            <label for="area">Área / Unidad (opcional)</label>
            <select id="area" name="area" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="">Sin área definida</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="rol">Rol</label>
            <select id="rol" name="rol" style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
                <option value="estandar">Usuario estándar</option>
                <option value="admin">Administrador</option>
            </select>
        </div>
        <div class="campo">
            <label for="password">Contraseña temporal (mínimo 8 caracteres)</label>
            <div class="campo-password-wrap">
                <input id="password" name="password" type="password" minlength="8" required>
                <button type="button" class="btn-mostrar-password" onclick="toggleMostrarPassword('password', this)">👁</button>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin-top:6px; font-weight:400;">La persona deberá cambiarla por una propia la primera vez que inicie sesión.</p>
        </div>
        <button type="submit" class="btn-primario">Crear cuenta</button>
    </form>

    <div style="max-width:560px; background:var(--card-bg); padding:18px 20px; border-radius:14px; box-shadow:var(--shadow); margin-bottom:40px;">
        <h3 style="margin-top:0; color:var(--guinda);">☁️ Botón "Nextcloud"</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-top:-6px;">Un solo enlace para todo el personal. Al activarlo, a cada usuario le aparece el botón en su barra lateral; al desactivarlo, desaparece para todos.</p>
        <form method="post">
            <input type="hidden" name="accion" value="guardar_nextcloud">
            <div class="campo">
                <label for="url_nextcloud">Enlace de acceso a Nextcloud</label>
                <input id="url_nextcloud" name="url_nextcloud" type="text" value="<?= htmlspecialchars($nextcloud['url']) ?>" style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-family:var(--font-body); font-size:14px; background:var(--bg); color:var(--text);">
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <?php if ($nextcloud['activo']): ?>
                    <button type="submit" name="activo" value="" class="btn-secundario" style="border:none; cursor:pointer;">Desactivar botón</button>
                    <span style="font-size:12px; color:var(--text-muted);">🟢 Visible para todo el personal</span>
                <?php else: ?>
                    <button type="submit" name="activo" value="1" class="btn-primario" style="width:auto; padding:10px 18px;">Activar botón</button>
                    <span style="font-size:12px; color:var(--text-muted);">🔴 Oculto para todos</span>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h2>Cuentas existentes</h2>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:6px;">
        <input id="filtro-texto" type="search" placeholder="🔍 Buscar por nombre o usuario…" autocomplete="off" style="flex:1; min-width:220px; max-width:380px; padding:10px 14px; border:1px solid var(--border); border-radius:10px; font-size:15px; background:var(--bg); color:var(--text);">
        <select id="filtro-area" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todas las áreas</option>
            <option value="__sin__">Sin área definida</option>
            <?php foreach ($areas as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filtro-rol" style="padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:var(--bg); color:var(--text);">
            <option value="">Todos los roles</option>
            <option value="admin">Administradores</option>
            <option value="estandar">Estándar</option>
        </select>
        <button type="button" id="filtro-limpiar" class="btn-secundario" style="border:none; cursor:pointer;">Limpiar</button>
    </div>
    <p id="filtro-contador" style="font-size:13px; color:var(--text-muted); margin:4px 0 14px;"></p>
    <p id="filtro-vacio" style="display:none; color:var(--text-muted);">No hay cuentas que coincidan con la búsqueda.</p>

    <div class="contenedor-acciones">
        <?php foreach ($usuarios as $u): ?>
            <div class="caja-accion tarjeta-usuario" data-buscar="<?= htmlspecialchars(mb_strtolower($u['nombre'] . ' ' . $u['usuario'] . ' ' . ($u['area'] ?? ''))) ?>" data-area="<?= htmlspecialchars($u['area'] ?? '') ?>" data-rol="<?= htmlspecialchars($u['rol']) ?>">
                <h3><?= htmlspecialchars($u['nombre']) ?></h3>
                <p>Usuario: <strong><?= htmlspecialchars($u['usuario']) ?></strong></p>
                <p>Rol: <?= $u['rol'] === 'admin' ? 'Administrador' : 'Estándar' ?></p>
                <p><?= $u['activo'] ? '🟢 Activo' : '🔴 Desactivado' ?></p>

                <details style="margin-top:8px; text-align:left;">
                    <summary class="btn-secundario" style="cursor:pointer; display:block; text-align:center; list-style:none;">✏️ Editar datos</summary>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="accion" value="editar_perfil">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <label style="font-size:12px; font-weight:700;">Nombre completo</label>
                        <input name="nombre" type="text" required value="<?= htmlspecialchars($u['nombre']) ?>" style="width:100%; margin-bottom:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                        <label style="font-size:12px; font-weight:700;">Usuario para iniciar sesión (sin espacios)</label>
                        <input name="usuario" type="text" required maxlength="50" value="<?= htmlspecialchars($u['usuario']) ?>" oninput="this.value = this.value.replace(/\s/g, '')" style="width:100%; margin-bottom:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                        <label style="font-size:12px; font-weight:700;">Correo</label>
                        <input name="correo" type="text" value="<?= htmlspecialchars($u['correo'] ?? '') ?>" style="width:100%; margin-bottom:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                        <button type="submit" class="btn-primario" style="padding:8px 12px; font-size:14px;" onclick="return confirm('¿Guardar los cambios en esta cuenta? Si cambias el usuario, la persona deberá usar el nuevo para entrar.');">Guardar cambios</button>
                    </form>
                </details>

                <?php if ((int) $u['id'] !== (int) $yo['id']): ?>
                <details style="margin-top:8px; text-align:left;">
                    <summary class="btn-secundario" style="cursor:pointer; display:block; text-align:center; list-style:none;">🔐 Cambiar rol</summary>
                    <form method="post" style="margin-top:10px;" onsubmit="return confirm('¿Cambiar el rol de <?= htmlspecialchars(addslashes($u['nombre'])) ?>?');">
                        <input type="hidden" name="accion" value="cambiar_rol">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <label style="font-size:12px; font-weight:700;">Nuevo rol</label>
                        <select name="nuevo_rol" style="width:100%; margin-bottom:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                            <option value="estandar" <?= $u['rol'] === 'estandar' ? 'selected' : '' ?>>Usuario estándar</option>
                            <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                        <label style="font-size:12px; font-weight:700;">Tu contraseña de administrador (para confirmar)</label>
                        <input name="password_admin" type="password" required autocomplete="current-password" style="width:100%; margin-bottom:8px; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--bg); color:var(--text);">
                        <button type="submit" class="btn-primario" style="padding:8px 12px; font-size:14px;">Cambiar rol</button>
                    </form>
                </details>
                <?php endif; ?>

                <form method="post" style="margin-top:8px; display:flex; gap:6px;">
                    <input type="hidden" name="accion" value="cambiar_area">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <select name="nueva_area" style="flex:1; min-width:0; padding:8px; border-radius:8px; border:1px solid var(--border); background:var(--bg); color:var(--text);">
                        <option value="" <?= empty($u['area']) ? 'selected' : '' ?>>Sin área definida</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= $u['area'] === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-secundario" style="flex-shrink:0; padding:8px 12px; border:none; cursor:pointer;" title="Guardar área">💾</button>
                </form>

                <form method="post" style="margin-top:8px; display:flex; gap:6px;">
                    <input type="hidden" name="accion" value="restablecer">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="text" name="nueva_password" class="campo-nueva-password" placeholder="Nueva contraseña" minlength="8" required style="flex:1; min-width:0; padding:8px; border-radius:8px; border:1px solid var(--border); font-family:var(--font-mono);">
                    <button type="button" class="btn-secundario" style="flex-shrink:0; padding:8px 12px; border:none; cursor:pointer;" onclick="generarPassword(this)" title="Generar una contraseña automática">🎲</button>
                    <button type="submit" class="btn-secundario" style="flex-shrink:0; padding:8px 12px; border:none; cursor:pointer;" onclick="return confirm('¿Restablecer la contraseña de <?= htmlspecialchars(addslashes($u['nombre'])) ?>?');">🔑</button>
                </form>

                <form method="post" style="margin-top:8px;" onsubmit="return confirm('¿Borrar todas las notas personales de calendario de <?= htmlspecialchars(addslashes($u['nombre'])) ?>? Es su agenda privada; esto no se puede deshacer.');">
                    <input type="hidden" name="accion" value="borrar_notas_calendario">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn-secundario" style="border:none; cursor:pointer; width:100%;">🗓️ Borrar sus notas de calendario</button>
                </form>

                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:8px;">
                    <?php if ((int) $u['id'] !== (int) $yo['id']): ?>
                    <form method="post" onsubmit="return confirm('¿Cambiar el estado de esta cuenta?');">
                        <input type="hidden" name="accion" value="estado">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn-secundario" style="border:none; cursor:pointer;">
                            <?= $u['activo'] ? 'Desactivar' : 'Reactivar' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ((int) $u['id'] !== (int) $yo['id']): ?>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta cuenta para siempre? Solo funciona si la persona no tiene tickets ni comunicados registrados.');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" style="border:none; cursor:pointer; background:none; color:var(--danger, #B23A3A); font-size:12px; text-decoration:underline;">🗑 Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    (function () {
        const quitarAcentos = t => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        const tarjetas = Array.from(document.querySelectorAll('.tarjeta-usuario'));
        const texto = document.getElementById('filtro-texto');
        const area = document.getElementById('filtro-area');
        const rol = document.getElementById('filtro-rol');
        const contador = document.getElementById('filtro-contador');
        const vacio = document.getElementById('filtro-vacio');

        function filtrar() {
            const q = quitarAcentos(texto.value.trim());
            let visibles = 0;
            tarjetas.forEach(t => {
                const coincideTexto = !q || quitarAcentos(t.dataset.buscar).includes(q);
                const coincideArea = !area.value || (area.value === '__sin__' ? t.dataset.area === '' : t.dataset.area === area.value);
                const coincideRol = !rol.value || t.dataset.rol === rol.value;
                const mostrar = coincideTexto && coincideArea && coincideRol;
                t.style.display = mostrar ? '' : 'none';
                if (mostrar) visibles++;
            });
            contador.textContent = 'Mostrando ' + visibles + ' de ' + tarjetas.length + ' cuentas';
            vacio.style.display = visibles === 0 ? '' : 'none';
        }

        texto.addEventListener('input', filtrar);
        area.addEventListener('change', filtrar);
        rol.addEventListener('change', filtrar);
        document.getElementById('filtro-limpiar').addEventListener('click', () => {
            texto.value = ''; area.value = ''; rol.value = '';
            filtrar();
        });
        filtrar();
    })();
</script>
<script>
    function generarPassword(boton) {
        const alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        let clave = '';
        for (let i = 0; i < 10; i++) clave += alfabeto[Math.floor(Math.random() * alfabeto.length)];
        const input = boton.parentElement.querySelector('.campo-nueva-password');
        input.value = clave;
    }
</script>
<script src="js/nav.js?v=3"></script>
<script src="js/nav-loader.js?v=30"></script>
</body>
</html>
