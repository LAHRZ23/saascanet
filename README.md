# SAASCANET: intranet institucional con mesa de servicios

Intranet que desarrollé para el **SAASCAEM**, un organismo público del Estado de México, y que hoy usan unas **110 personas**. Convierte procesos que antes eran informales (pedir soporte técnico por teléfono o WhatsApp, avisar algo urgente, llevar el calendario) en procesos **registrados, medibles y con responsables**.

> Código publicado con autorización de la Unidad de Apoyo Técnico e Informático del SAASCAEM. Esta versión **no incluye datos reales**: se quitaron archivos de usuarios, documentos internos, multimedia institucional y credenciales.

📊 **Análisis de los datos que genera el sistema:** [saascanet-analisis-soporte](https://github.com/LAHRZ23/saascanet-analisis-soporte)

## El problema

- Las solicitudes de soporte llegaban por llamada, en el pasillo o por WhatsApp: no quedaba registro de quién atendía, cuánto tardaba ni qué áreas necesitaban más apoyo.
- Los comunicados urgentes dependían de que alguien leyera un correo o un grupo de mensajes.
- No había evidencia ordenada para auditorías ni control de cambios administrativos.

## Qué hace

| Módulo | Para qué sirve |
|---|---|
| **Mesa de servicios (tickets)** | Cada solicitud tiene folio, área, tipo, estatus, responsable, participantes, notas y tiempos de registro y resolución. Se puede imprimir y exportar. |
| **Reportes** | Tickets por área, tipo y técnico, con exportación para reportes de la Unidad. |
| **Bitácora** | Registra quién publica, cambia roles o elimina cuentas, para control interno y auditorías. |
| **Comunicados y avisos** | Comunicados con imagen o PDF, avisos emergentes y conteo de vistos. |
| **Calendario institucional** | Eventos, días no laborables, calendario oficial y notas personales. |
| **Capacitación** | Cursos con vigencia y tutoriales. |
| **Encuestas** | Encuesta de la fase beta y evaluación de la calidad de la atención. |
| **Directorio, cumpleaños y solicitudes de publicación** | Herramientas internas para el personal. |
| **Administración** | Usuarios, roles, temas visuales y configuración. |

## Base de datos

MySQL con **30 tablas**. El diseño evolucionó con el uso: la carpeta `sql/` tiene el esquema inicial (`schema.sql`) y **37 migraciones** numeradas, una por cada mejora. Por ejemplo, `actualizacion_36_ticket_participantes.sql` agregó la posibilidad de registrar a varios técnicos por ticket.

Tablas principales: `usuarios`, `tickets`, `ticket_participantes`, `bitacora`, `comunicados`, `comunicados_vistos`, `calendario_eventos`, `cursos`, `encuesta_respuestas`, `calidad_respuestas`, `solicitudes_publicacion`.

Decisiones de diseño:

- **Pensada para medir:** cada ticket guarda responsable y tiempos, para poder reportar demanda por área y tiempos de resolución.
- **Bitácora de auditoría** separada de los datos operativos.
- **Contraseñas con `password_hash`** y consultas preparadas (`mysqli_prepare`) para evitar inyección SQL.
- **Migraciones numeradas** en lugar de modificar la base a mano, para poder repetir los cambios en otro servidor.

## Tecnologías

PHP · MySQL · JavaScript · HTML/CSS · XAMPP (Apache)

## Cómo instalarlo (local, con XAMPP)

1. Copia la carpeta en `C:\xampp\htdocs\saascanet` y enciende **Apache** y **MySQL** en XAMPP.
2. En phpMyAdmin crea la base `soporte` y ejecuta `sql/schema.sql` y después las migraciones `sql/actualizacion_1...37` **en orden numérico**.
3. Copia `config.example.php` como `config.php` y pon tu usuario y contraseña de MySQL.
4. Abre `http://localhost/saascanet/instalar.php` para crear la primera cuenta de administrador.
5. Entra en `http://localhost/saascanet/`.

> Los datos del directorio (correos y extensiones) son de ejemplo. La multimedia institucional (videos de tutoriales, banners y logotipo) no se incluye.

## Desarrollo

Desarrollé el sistema con apoyo de un asistente de IA (Claude Code) para escribir y revisar código. Yo definí los requerimientos con los usuarios, diseñé la base de datos, probé cada módulo y lo puse en operación. La fase beta incluyó una encuesta dentro de la intranet para priorizar mejoras.

## Autor

**Luis Antonio Hernández Ruiz**, Unidad de Apoyo Técnico e Informático
[Portafolio](https://lahrz23.github.io) · [LinkedIn](https://www.linkedin.com/in/luis-antonio-hern%C3%A1ndez-ruiz-1abb21185/) · [GitHub](https://github.com/LAHRZ23)
