<?php
/**
 * seguimiento_proyecto.php
 * 
 * Herramienta mejorada para sincronizar y hacer seguimiento del desarrollo del Sistema ERP KEYDAN
 * Analiza la estructura actual de la base de datos, archivos del proyecto y genera reportes
 * de estado y continuidad para facilitar el desarrollo entre sesiones de chat con IA.
 * 
 * @version 1.4
 * @fecha 30/03/2025
 */

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuración
define('TITULO_SISTEMA', 'Sistema ERP KEYDAN');
define('VERSION_ACTUAL', '0.1.0');

// Incluir configuración de base de datos
try {
    require_once 'config/db.php';
} catch (Exception $e) {
    die("Error al incluir config/db.php: " . $e->getMessage());
}

// Ensure $conn is defined
if (!isset($conn)) {
    die("Error: La conexión a la base de datos no está definida. Verifica config/db.php.");
}

// Iniciar sesión para almacenar elementos completados
session_start();

// Manejar formulario de elementos completados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['complete_tabla']) || isset($_POST['complete_archivo']))) {
    if (!isset($_SESSION['tablas_completadas'])) {
        $_SESSION['tablas_completadas'] = [];
    }
    if (!isset($_SESSION['archivos_completados'])) {
        $_SESSION['archivos_completados'] = [];
    }
    
    if (isset($_POST['complete_tabla'])) {
        $_SESSION['tablas_completadas'] = array_unique(array_merge($_SESSION['tablas_completadas'], $_POST['complete_tabla']));
    }
    
    if (isset($_POST['complete_archivo'])) {
        $_SESSION['archivos_completados'] = array_unique(array_merge($_SESSION['archivos_completados'], $_POST['complete_archivo']));
    }
    
    // Redirigir para actualizar la página
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Manejar AJAX request para dynamic prompt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['selected_module']) || isset($_POST['selected_task']))) {
    $selectedModule = isset($_POST['selected_module']) ? $_POST['selected_module'] : null;
    $selectedTask = isset($_POST['selected_task']) ? $_POST['selected_task'] : null;
    
    // Analizar base de datos
    $estructuraDB = analizarBaseDatos($conn);
    if (isset($estructuraDB['error']) && $estructuraDB['error']) {
        die("Error al analizar la base de datos: " . $estructuraDB['mensaje']);
    }
    
    // Analizar archivos
    $estructuraArchivos = analizarArchivos();
    
    // Analizar progreso
    $progreso = analizarProgreso($estructuraDB, $estructuraArchivos);
    
    // Generar continuidad con el módulo o tarea seleccionada
    $continuidad = generarContinuidad($progreso, $estructuraDB, $selectedModule, $selectedTask);
    
    // Generar HTML para archivos esenciales
    $filesHtml = '<ul class="mb-3">';
    foreach ($continuidad['archivos_clave'] as $archivo) {
        $filesHtml .= '<li><i class="fas fa-file-code mr-2 text-primary"></i> ' . htmlspecialchars($archivo) . '</li>';
    }
    $filesHtml .= '</ul>';
    
    // Enviar respuesta JSON
    header('Content-Type: application/json');
    echo json_encode([
        'prompt' => $continuidad['prompt'],
        'files' => $filesHtml
    ]);
    exit;
}

// Función para obtener encabezado HTML
function getHeader($titulo) {
    return '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($titulo) . ' - Seguimiento</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.3/css/all.min.css">
        <style>
            :root {
                --primary-color: #0056b3;
                --secondary-color: #6c757d;
                --success-color: #28a745;
                --warning-color: #ffc107;
                --danger-color: #dc3545;
                --info-color: #17a2b8;
                --light-color: #f8f9fa;
                --dark-color: #343a40;
            }
            body {
                font-family: "Segoe UI", Roboto, Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
                padding-top: 56px;
            }
            .container-fluid {
                padding: 0 30px;
            }
            .sidebar {
                position: fixed;
                top: 56px;
                bottom: 0;
                left: 0;
                width: 260px;
                padding: 20px 0;
                overflow-y: auto;
                background-color: #fff;
                box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
                z-index: 100;
            }
            .sidebar .nav-link {
                color: #333;
                border-left: 3px solid transparent;
                padding: 10px 20px;
                font-weight: 500;
            }
            .sidebar .nav-link:hover {
                background-color: #f8f9fa;
                border-left-color: #ddd;
            }
            .sidebar .nav-link.active {
                color: var(--primary-color);
                border-left-color: var(--primary-color);
                background-color: #e8f0fd;
            }
            .sidebar .nav-link i {
                margin-right: 10px;
                width: 20px;
                text-align: center;
            }
            .main-content {
                margin-left: 260px;
                padding: 30px;
            }
            .card {
                border: none;
                box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
                margin-bottom: 24px;
                border-radius: 8px;
            }
            .card-header {
                background-color: #fff;
                border-bottom: 1px solid rgba(0,0,0,0.1);
                padding: 15px 20px;
                font-weight: 600;
            }
            .progress {
                height: 10px;
                border-radius: 5px;
            }
            .module-complete {
                border-left: 4px solid var(--success-color);
            }
            .module-in-progress {
                border-left: 4px solid var(--warning-color);
            }
            .module-pending {
                border-left: 4px solid var(--secondary-color);
            }
            .table-structure {
                font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 0.85em;
            }
            .code-block {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #ddd;
                font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                white-space: pre-wrap;
                font-size: 0.9em;
            }
            .context-files {
                background-color: #e8f0fd;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                border: 1px solid #c1d5f4;
            }
            .dependency-item {
                display: flex;
                margin-bottom: 1.5rem;
                padding-bottom: 1.5rem;
                border-bottom: 1px solid rgba(0,0,0,0.1);
            }
            .dependency-item:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }
            .dependency-icon {
                width: 50px;
                height: 50px;
                margin-right: 15px;
                background-color: #e8f0fd;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                color: var(--primary-color);
                flex-shrink: 0;
            }
            .dependency-content {
                flex-grow: 1;
            }
            .dependency-title {
                font-weight: 600;
                margin-bottom: 5px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .alert-custom {
                border-radius: 8px;
                padding: 15px 20px;
            }
            .alert-custom .alert-icon {
                margin-right: 15px;
                font-size: 1.5rem;
            }
            .badge-status {
                padding: 5px 10px;
                border-radius: 20px;
                font-weight: 500;
                font-size: 0.75rem;
            }
            .navbar-brand {
                font-weight: 600;
                font-size: 1.25rem;
            }
            .sticky-alert {
                position: sticky;
                top: 20px;
                z-index: 99;
            }
            .tab-content-custom {
                padding: 20px;
                border: 1px solid #dee2e6;
                border-top: none;
                border-radius: 0 0 8px 8px;
                background-color: #fff;
            }
            .nav-tabs .nav-link {
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
            }
            .item-action {
                opacity: 0.5;
                transition: opacity 0.2s;
            }
            .list-group-item:hover .item-action {
                opacity: 1;
            }
            .progress-label {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                font-weight: 500;
                font-size: 0.9rem;
            }
            .dependency-files {
                margin-top: 10px;
                font-size: 0.9em;
                background-color: #f5f5f5;
                padding: 10px;
                border-radius: 4px;
            }
            .navbar-dark .navbar-nav .nav-link {
                color: rgba(255,255,255,0.8);
            }
            .navbar-dark .navbar-nav .nav-link:hover {
                color: #fff;
            }
            #prompt-textarea {
                min-height: 150px;
                font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 0.9em;
            }
            .tooltip-icon {
                margin-left: 5px;
                color: #6c757d;
                cursor: help;
            }
            .status-icon {
                font-size: 0.85em;
                margin-right: 5px;
            }
            .documentation-link {
                text-decoration: none;
                color: inherit;
            }
            .documentation-link:hover {
                text-decoration: none;
            }
            .documentation-card {
                transition: transform 0.3s, box-shadow 0.3s;
            }
            .documentation-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
            }
            .footer {
                font-size: 0.9rem;
                color: #6c757d;
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #dee2e6;
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <i class="fas fa-project-diagram mr-2"></i>' . TITULO_SISTEMA . '
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="refresh-data">
                                <i class="fas fa-sync-alt mr-1"></i> Actualizar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?formato=json" target="_blank">
                                <i class="fas fa-code mr-1"></i> API JSON
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#configuracion" data-toggle="modal" data-target="#configModal">
                                <i class="fas fa-cog mr-1"></i> Configuración
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <div class="sidebar">
            <div class="text-center mb-4">
                <span class="badge badge-pill badge-primary p-2">v' . VERSION_ACTUAL . '</span>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="#dashboard" data-section="dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#continuidad" data-section="continuidad">
                        <i class="fas fa-sync-alt"></i> Continuidad
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#dependencias" data-section="dependencias">
                        <i class="fas fa-project-diagram"></i> Dependencias
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#base-datos" data-section="base-datos">
                        <i class="fas fa-database"></i> Base de Datos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#relaciones-archivos-tablas" data-section="relaciones-archivos-tablas">
                                <i class="fas fa-link"></i> Relaciones Archivos/Tablas
                            </a>
                        </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="#relaciones-archivos" data-section="relaciones-archivos">
                    <i class="fas fa-file-code"></i> Relaciones Archivos PHP
                </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#documentacion" data-section="documentacion">
                        <i class="fas fa-book"></i> Documentación
                    </a>
                </li>
            </ul>
            
            <hr>
            
            <div class="px-3">
                <h6 class="text-uppercase text-muted mb-3">
                    <small>Progreso general</small>
                </h6>
                
                <div class="mb-4">
                    <div class="progress-label">
                        <span>Fase 1</span>
                        <span>25%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: 25%"></div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="progress-label">
                        <span>Fase 2</span>
                        <span>0%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-secondary" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="progress-label">
                        <span>Fase 3</span>
                        <span>0%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-secondary" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="main-content">';
}

// Función para obtener pie de página HTML
function getFooter() {
    return '
            <footer class="footer">
                <p>Herramienta de seguimiento - ' . TITULO_SISTEMA . '</p>
                <p>© KEYDAN SAC - ' . date('Y') . '</p>
            </footer>
        </div>
        
        <!-- Modal de configuración -->
        <div class="modal fade" id="configModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Configuración</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="config-theme">Tema</label>
                            <select class="form-control" id="config-theme">
                                <option value="light">Claro</option>
                                <option value="dark">Oscuro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="config-refresh">Actualización automática</label>
                            <select class="form-control" id="config-refresh">
                                <option value="0">Desactivada</option>
                                <option value="60">Cada minuto</option>
                                <option value="300">Cada 5 minutos</option>
                                <option value="600">Cada 10 minutos</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
        <script>
            $(document).ready(function() {
                // Activar tooltips
                $("[data-toggle=\'tooltip\']").tooltip();
                
                // Navegación del sidebar
                $(".sidebar .nav-link").on("click", function(e) {
                    e.preventDefault();
                    
                    // Activar enlace
                    $(".sidebar .nav-link").removeClass("active");
                    $(this).addClass("active");
                    
                    // Mostrar sección
                    const target = $(this).data("section");
                    $("section.content-section").hide();
                    $("#" + target).show();
                });
                
                // Navegación de detalles en módulos
                $(".btn-details").on("click", function(e) {
                    e.preventDefault();
                    const target = $(this).attr("href").substring(1); // Remove the #
                    $(".sidebar .nav-link").removeClass("active");
                    $(".sidebar .nav-link[data-section=\'dependencias\']").addClass("active");
                    $("section.content-section").hide();
                    $("#dependencias").show();
                    // Scroll to the specific module
                    $("html, body").animate({
                        scrollTop: $("#" + target).offset().top - 100
                    }, 500);
                });
                
                // Copiar prompt al portapapeles
                $("#copy-prompt").on("click", function() {
                    const promptText = $("#prompt-textarea").val();
                    navigator.clipboard.writeText(promptText).then(function() {
                        $("#copy-prompt").html("<i class=\'fas fa-check\'></i> ¡Copiado!");
                        setTimeout(function() {
                            $("#copy-prompt").html("<i class=\'fas fa-copy\'></i> Copiar");
                        }, 2000);
                    });
                });
                
                // Copiar lista de archivos
                $(".copy-files").on("click", function() {
                    const filesList = $(this).closest(".context-files").find("ul").text().trim();
                    navigator.clipboard.writeText(filesList).then(function() {
                        $(this).html("<i class=\'fas fa-check\'></i> ¡Copiado!");
                        setTimeout(() => {
                            $(this).html("<i class=\'fas fa-clipboard-list mr-1\'></i> Copiar lista de archivos");
                        }, 2000);
                    });
                });
                
                // Exportar SQL (simulado)
                $("#export-sql").on("click", function(e) {
                    e.preventDefault();
                    alert("Exportando SQL... (Funcionalidad simulada)");
                    // Aquí puedes implementar la lógica real para exportar SQL
                });
                
                // Exportar Diagrama ER (simulado)
                $("#export-diagram").on("click", function(e) {
                    e.preventDefault();
                    alert("Exportando Diagrama ER... (Funcionalidad simulada)");
                    // Aquí puedes implementar la lógica real para exportar el diagrama
                });
                
                // Exportar JSON (simulado)
                $("#export-json").on("click", function(e) {
                    e.preventDefault();
                    window.location.href = "?formato=json";
                });
                
                // Imprimir documentación (simulado)
                $("#print-docs").on("click", function(e) {
                    e.preventDefault();
                    window.print();
                });
                
                // Actualizar datos
                $("#refresh-data").on("click", function(e) {
                    e.preventDefault();
                    location.reload();
                });
                
                // Filtrar tablas
                $("#search-table").on("keyup", function() {
                    const value = $(this).val().toLowerCase();
                    $("#accordionDB .card").filter(function() {
                        $(this).toggle($(this).find(".btn-link").text().toLowerCase().indexOf(value) > -1);
                    });
                });
                
                // Seleccionar enfoque para personalizar prompt
                $("#select-focus").on("change", function() {
                    const focus = $(this).val();
                    let selectedModule = null;
                    let selectedTask = null;
                    
                    if (focus.startsWith("modulo_")) {
                        selectedModule = focus.replace("modulo_", "");
                    } else if (focus.startsWith("tabla_") || focus.startsWith("archivo_")) {
                        selectedTask = focus;
                    }
                    
                    $.ajax({
                        url: window.location.href,
                        method: "POST",
                        data: { 
                            selected_module: selectedModule,
                            selected_task: selectedTask
                        },
                        success: function(response) {
                            $("#prompt-textarea").val(response.prompt);
                            $("#essential-files").html(response.files);
                        },
                        error: function() {
                            alert("Error al actualizar el prompt. Por favor, intenta de nuevo.");
                        }
                    });
                });
                
                // Por defecto, mostrar la primera sección
                $("section.content-section:first").show();
            });
        </script>
    </body>
    </html>';
}

/**
 * Analiza la estructura de la base de datos
 * @param PDO $conn Conexión a la base de datos
 * @return array Información sobre tablas y su estructura
 */
function analizarBaseDatos($conn) {
    try {
        // Obtener todas las tablas
        $stmt = $conn->query("SHOW TABLES");
        $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $estructuraDB = [];
        
        foreach ($tablas as $tabla) {
            // Obtener estructura de cada tabla
            $stmt = $conn->query("DESCRIBE `$tabla`");
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener claves foráneas
            $stmt = $conn->query("
                SELECT 
                    TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                    REFERENCED_TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '$tabla'
            ");
            $clavesForaneas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $estructuraDB[$tabla] = [
                'columnas' => $columnas,
                'claves_foraneas' => $clavesForaneas
            ];
        }
        
        return [
            'tablas' => $tablas,
            'estructura' => $estructuraDB
        ];
        
    } catch (PDOException $e) {
        return [
            'error' => true,
            'mensaje' => $e->getMessage()
        ];
    }
}

/**
 * Analiza la estructura de archivos del proyecto
 * @return array Información sobre archivos y directorios
 */
function analizarArchivos() {
    $directorio = './';  // Directorio actual
    $ignorar = ['.', '..', '.git', 'assets/vendor', 'node_modules', 'seguimiento_proyecto.php'];
    
    $archivos = ['directorios' => [], 'archivos' => []];
    $dirIterator = new RecursiveDirectoryIterator($directorio);
    $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
    
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        $relativePath = str_replace($directorio, '', $path);
        
        // Ignorar archivos y carpetas específicas
        $ignorado = false;
        foreach ($ignorar as $item) {
            if (strpos($relativePath, $item) === 0) {
                $ignorado = true;
                break;
            }
        }
        
        if (!$ignorado) {
            if ($file->isDir()) {
                $archivos['directorios'][] = $relativePath;
            } else {
                $extension = $file->getExtension();
                $archivo = [
                    'ruta' => $relativePath,
                    'tamaño' => $file->getSize(),
                    'modificado' => date('Y-m-d H:i:s', $file->getMTime())
                ];
                
                if (!isset($archivos['archivos'][$extension])) {
                    $archivos['archivos'][$extension] = [];
                }
                $archivos['archivos'][$extension][] = $archivo;
            }
        }
    }
    
    return $archivos;
}

/**
 * Genera un análisis del progreso del proyecto
 * @param array $estructuraDB Estructura de la base de datos
 * @param array $estructuraArchivos Estructura de archivos
 * @return array Información sobre el progreso
 */
function analizarProgreso($estructuraDB, $estructuraArchivos) {
    // Definir las tablas esperadas por fase según la documentación
    $tablasPorFase = [
        'Implementado' => [
            'clientes', 'materiales', 'cotizaciones', 'cotizacion_detalles', 
            'proformas', 'proforma_detalles', 'usuarios', 'medidas_estandar'
        ],
        'Fase 1' => [
            'ordenes_venta', 'orden_detalles', 'facturas', 'factura_detalles', 
            'pagos', 'notificaciones'
        ],
        'Fase 2' => [
            'ordenes_produccion', 'produccion_detalles', 'inventario_materiales', 
            'consumo_materiales', 'ordenes_compra', 'compra_detalles'
        ],
        'Fase 3' => [
            'inventario_productos', 'movimientos_inventario', 'despachos', 
            'despacho_detalles', 'vehiculos', 'rutas'
        ],
        'Fase 4' => [
            'tickets_soporte', 'seguimiento_tickets', 'encuestas', 
            'preguntas_encuesta', 'respuestas_encuesta', 'detalle_respuestas'
        ]
    ];
    
    // Archivos PHP esperados por módulo
    $archivosPorModulo = [
        'Clientes' => [
            'clientes.php', 'nuevo_cliente.php', 'editar_cliente.php', 'guardar_cliente.php'
        ],
        'Cotizaciones' => [
            'cotizaciones.php', 'nueva_cotizacion.php', 'editar_cotizacion.php', 
            'ver_cotizacion.php', 'guardar_cotizacion.php'
        ],
        'Proformas' => [
            'proformas.php', 'generar_proforma.php', 'ver_proforma.php', 
            'imprimir_proforma.php', 'aprobar_proforma.php'
        ],
        'Órdenes' => [
            'ordenes.php', 'ordenes_venta.php', 'ver_orden.php', 'completar_orden.php'
        ],
        'Materiales' => [
            'materiales.php', 'nuevo_material.php', 'editar_material.php'
        ],
        'Sistema' => [
            'index.php', 'login.php', 'dashboard.php', 'logout.php'
        ]
    ];
    
    // Verificar tablas existentes
    $tablasExistentes = isset($estructuraDB['tablas']) ? $estructuraDB['tablas'] : [];
    $progresoTablas = [];
    $tablasCompletadasManually = isset($_SESSION['tablas_completadas']) ? $_SESSION['tablas_completadas'] : [];
    
    foreach ($tablasPorFase as $fase => $tablas) {
        $totalTablas = count($tablas);
        $tablasCreadas = 0;
        $tablasDetalle = [];
        
        foreach ($tablas as $tabla) {
            $existe = in_array($tabla, $tablasExistentes) || in_array($tabla, $tablasCompletadasManually);
            $tablasDetalle[$tabla] = [
                'nombre' => $tabla,
                'existe' => $existe
            ];
            
                       if ($existe) {
                $tablasCreadas++;
            }
        }
        
        $porcentaje = ($totalTablas > 0) ? round(($tablasCreadas / $totalTablas) * 100) : 0;
        
        $progresoTablas[$fase] = [
            'total' => $totalTablas,
            'creadas' => $tablasCreadas,
            'porcentaje' => $porcentaje,
            'detalle' => $tablasDetalle
        ];
    }
    
    // Verificar archivos existentes
    $archivosExistentes = isset($estructuraArchivos['archivos']['php']) ? 
                         array_column($estructuraArchivos['archivos']['php'], 'ruta') : [];
    $archivosExistentesBasenames = array_map('basename', $archivosExistentes);
                         
    $progresoArchivos = [];
    $archivosCompletadosManually = isset($_SESSION['archivos_completados']) ? $_SESSION['archivos_completados'] : [];
    
    foreach ($archivosPorModulo as $modulo => $archivos) {
        $totalArchivos = count($archivos);
        $archivosCreados = 0;
        $archivosDetalle = [];
        
        foreach ($archivos as $archivo) {
            $existe = in_array($archivo, $archivosExistentesBasenames) || in_array($archivo, $archivosCompletadosManually);
            $archivosDetalle[$archivo] = [
                'nombre' => $archivo,
                'existe' => $existe
            ];
            
            if ($existe) {
                $archivosCreados++;
            }
        }
        
        $porcentaje = ($totalArchivos > 0) ? round(($archivosCreados / $totalArchivos) * 100) : 0;
        
        $progresoArchivos[$modulo] = [
            'total' => $totalArchivos,
            'creados' => $archivosCreados,
            'porcentaje' => $porcentaje,
            'detalle' => $archivosDetalle
        ];
    }
    
    // Calcular progreso general
    $totalTablasPendientes = 0;
    $totalTablasCreadas = 0;
    
    foreach ($progresoTablas as $fase => $datos) {
        $totalTablasPendientes += $datos['total'];
        $totalTablasCreadas += $datos['creadas'];
    }
    
    $progresoGeneral = ($totalTablasPendientes > 0) ? 
                      round(($totalTablasCreadas / $totalTablasPendientes) * 100) : 0;
    
    return [
        'tablas' => $progresoTablas,
        'archivos' => $progresoArchivos,
        'general' => $progresoGeneral
    ];
}

/**
 * Define las dependencias y prerequisitos para cada funcionalidad
 * @return array Información sobre dependencias
 */
function definirDependencias() {
    return [
        'cotizaciones' => [
            'nombre' => 'Módulo de Cotizaciones',
            'estado' => 'implementado',
            'dependencias' => [
                ['tipo' => 'tabla', 'nombre' => 'clientes', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'materiales', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'cotizaciones', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'cotizacion_detalles', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'cotizaciones.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'nueva_cotizacion.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'guardar_cotizacion.php', 'obligatorio' => true],
            ],
            'icono' => 'fas fa-file-invoice',
            'archivos_clave' => ['cotizaciones.php', 'nueva_cotizacion.php', 'guardar_cotizacion.php'],
            'descripcion' => 'Gestión de cotizaciones para clientes.',
            'instrucciones' => 'Para el correcto funcionamiento del módulo de cotizaciones, es necesario tener configuradas las tablas de clientes y materiales. El sistema calcula automáticamente los precios según las fórmulas definidas.'
        ],
        'proformas' => [
            'nombre' => 'Módulo de Proformas',
            'estado' => 'implementado',
            'dependencias' => [
                ['tipo' => 'modulo', 'nombre' => 'cotizaciones', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'proformas', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'proforma_detalles', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'proformas.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'generar_proforma.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'ver_proforma.php', 'obligatorio' => true],
            ],
            'icono' => 'fas fa-file-alt',
            'archivos_clave' => ['proformas.php', 'generar_proforma.php', 'ver_proforma.php', 'imprimir_proforma.php'],
            'descripcion' => 'Generación y gestión de proformas a partir de cotizaciones.',
            'instrucciones' => 'Las proformas son generadas a partir de cotizaciones aprobadas. Es necesario tener implementado completamente el módulo de cotizaciones.'
        ],
        'ordenes_venta' => [
            'nombre' => 'Módulo de Órdenes de Venta',
            'estado' => 'en_progreso',
            'dependencias' => [
                ['tipo' => 'modulo', 'nombre' => 'proformas', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'ordenes_venta', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'orden_detalles', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'ordenes.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'ordenes_venta.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'ver_orden.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'completar_orden.php', 'obligatorio' => false],
            ],
            'icono' => 'fas fa-shopping-cart',
            'archivos_clave' => ['ordenes.php', 'ordenes_venta.php', 'ver_orden.php', 'completar_orden.php'],
            'descripcion' => 'Gestión de órdenes de venta generadas a partir de proformas.',
            'instrucciones' => 'Para implementar correctamente el módulo de órdenes de venta, debe añadir en la tabla ordenes_venta los campos para seguimiento de estados (estado_produccion, estado_pago, estado_entrega) y prioridad. Asegúrese de implementar la lógica para cambios de estado.'
        ],
        'facturacion' => [
            'nombre' => 'Módulo de Facturación',
            'estado' => 'pendiente',
            'dependencias' => [
                ['tipo' => 'modulo', 'nombre' => 'ordenes_venta', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'facturas', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'factura_detalles', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'facturas.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'generar_factura.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'ver_factura.php', 'obligatorio' => true],
            ],
            'icono' => 'fas fa-file-invoice-dollar',
            'archivos_clave' => ['facturas.php', 'generar_factura.php', 'ver_factura.php', 'imprimir_factura.php'],
            'descripcion' => 'Generación y gestión de facturas a partir de órdenes de venta.',
            'instrucciones' => 'Este módulo requiere la creación de las tablas facturas y factura_detalles. Es importante implementar la lógica de conversión entre órdenes de venta y facturas, respetando la información fiscal.'
        ],
        'pagos' => [
            'nombre' => 'Módulo de Pagos',
            'estado' => 'pendiente',
            'dependencias' => [
                ['tipo' => 'modulo', 'nombre' => 'facturacion', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'pagos', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'pagos.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'registrar_pago.php', 'obligatorio' => true],
            ],
            'icono' => 'fas fa-money-bill-wave',
            'archivos_clave' => ['pagos.php', 'registrar_pago.php', 'reporte_cuentas_cobrar.php'],
            'descripcion' => 'Registro y seguimiento de pagos asociados a facturas.',
            'instrucciones' => 'Cree la tabla pagos con los campos necesarios para registrar la información de pagos. Desarrolle la lógica para actualizar el estado de las facturas cuando se registren pagos parciales o completos.'
        ],
        'produccion' => [
            'nombre' => 'Módulo de Producción',
            'estado' => 'pendiente',
            'dependencias' => [
                ['tipo' => 'modulo', 'nombre' => 'ordenes_venta', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'ordenes_produccion', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'produccion_detalles', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'ordenes_produccion.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'planificador_produccion.php', 'obligatorio' => true],
            ],
            'icono' => 'fas fa-industry',
            'archivos_clave' => ['ordenes_produccion.php', 'planificador_produccion.php', 'seguimiento_produccion.php'],
            'descripcion' => 'Planificación y seguimiento de la producción asociada a órdenes de venta.',
            'instrucciones' => 'Este módulo requiere la implementación de las tablas ordenes_produccion y produccion_detalles. Desarrolle la lógica para crear órdenes de producción a partir de órdenes de venta y actualizar los estados respectivos.'
        ],
        'inventario' => [
            'nombre' => 'Módulo de Inventario',
            'estado' => 'pendiente',
            'dependencias' => [
                ['tipo' => 'modulo', 'nombre' => 'produccion', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'inventario_materiales', 'obligatorio' => true],
                ['tipo' => 'tabla', 'nombre' => 'consumo_materiales', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'inventario.php', 'obligatorio' => true],
                ['tipo' => 'archivo', 'nombre' => 'registro_consumo.php', 'obligatorio' => true],
            ],
            'icono' => 'fas fa-boxes',
            'archivos_clave' => ['inventario.php', 'registro_consumo.php', 'reportes_consumo.php'],
            'descripcion' => 'Gestión de inventario de materiales y control de consumo.',
            'instrucciones' => 'Implemente las tablas inventario_materiales y consumo_materiales. Desarrolle la lógica para registrar el consumo de materiales en el proceso de producción y actualizar el inventario.'
        ]
    ];
}

/**
 * Genera instrucciones de continuidad para un nuevo chat
 * @param array $progreso Información sobre el progreso
 * @param array $estructuraDB Estructura de la base de datos
 * @param string|null $selectedModule Módulo seleccionado para personalizar el prompt
 * @param string|null $selectedTask Tarea específica seleccionada (tabla o archivo)
 * @return array Información para continuidad
 */
function generarContinuidad($progreso, $estructuraDB, $selectedModule = null, $selectedTask = null) {
    // Determinar la fase actual
    $faseActual = 'Implementado';
    $siguienteFase = 'Fase 1';
    $porcentajeFaseActual = 100;
    
    foreach ($progreso['tablas'] as $fase => $datos) {
        if ($fase != 'Implementado' && $datos['porcentaje'] < 100) {
            $faseActual = $fase;
            $porcentajeFaseActual = $datos['porcentaje'];
            
            // Determinar siguiente fase
            $fases = array_keys($progreso['tablas']);
            $indiceActual = array_search($fase, $fases);
            $siguienteFase = isset($fases[$indiceActual + 1]) ? $fases[$indiceActual + 1] : null;
            
            break;
        }
    }
    
    // Identificar próximas tablas a crear
    $proximasTablas = [];
    $tablasCompletadasManually = isset($_SESSION['tablas_completadas']) ? $_SESSION['tablas_completadas'] : [];
    
    if (isset($progreso['tablas'][$faseActual])) {
        foreach ($progreso['tablas'][$faseActual]['detalle'] as $tabla => $info) {
            if (!$info['existe'] && !in_array($tabla, $tablasCompletadasManually)) {
                $proximasTablas[] = $tabla;
            }
        }
    }
    
    // Determinar módulos en progreso
    $modulosEnProgreso = [];
    
    foreach ($progreso['archivos'] as $modulo => $datos) {
        if ($datos['porcentaje'] > 0 && $datos['porcentaje'] < 100) {
            $modulosEnProgreso[] = $modulo;
        }
    }
    
    // Generar lista de archivos clave
    $archivosClave = [
        'config/db.php',
        'seguimiento_proyecto.php'
    ];
    
    if ($selectedModule && isset($progreso['archivos'][$selectedModule])) {
        foreach ($progreso['archivos'][$selectedModule]['detalle'] as $archivo => $info) {
            if ($info['existe']) {
                $archivosClave[] = $archivo;
            }
        }
    } elseif ($selectedTask) {
        // Si se selecciona una tarea específica (tabla o archivo)
        if (strpos($selectedTask, 'tabla_') === 0) {
            $tabla = str_replace('tabla_', '', $selectedTask);
            if (strpos($tabla, 'orden') !== false) {
                $archivosClave[] = 'ordenes.php';
                $archivosClave[] = 'ordenes_venta.php';
                $archivosClave[] = 'ver_orden.php';
            } elseif (strpos($tabla, 'factura') !== false) {
                $archivosClave[] = 'facturas.php';
                $archivosClave[] = 'generar_factura.php';
            } elseif (strpos($tabla, 'pago') !== false) {
                $archivosClave[] = 'pagos.php';
                $archivosClave[] = 'registrar_pago.php';
            }
        } elseif (strpos($selectedTask, 'archivo_') === 0) {
            $archivo = str_replace('archivo_', '', $selectedTask);
            $archivosClave[] = $archivo;
        }
    } else {
        // Agregar archivos de la fase actual
        if (!empty($proximasTablas) && count($proximasTablas) < 3) {
            foreach ($proximasTablas as $tabla) {
                if (strpos($tabla, 'orden') !== false) {
                    $archivosClave[] = 'ordenes.php';
                    $archivosClave[] = 'ordenes_venta.php';
                    $archivosClave[] = 'ver_orden.php';
                } elseif (strpos($tabla, 'factura') !== false) {
                    $archivosClave[] = 'facturas.php';
                    $archivosClave[] = 'generar_factura.php';
                } elseif (strpos($tabla, 'pago') !== false) {
                    $archivosClave[] = 'pagos.php';
                    $archivosClave[] = 'registrar_pago.php';
                }
            }
        }
        
        // Incluir archivos de módulos en progreso
        foreach ($modulosEnProgreso as $modulo) {
            if (isset($progreso['archivos'][$modulo])) {
                foreach ($progreso['archivos'][$modulo]['detalle'] as $archivo => $info) {
                    if ($info['existe']) {
                        $archivosClave[] = $archivo;
                    }
                }
            }
        }
    }
    
    // Eliminar duplicados
    $archivosClave = array_unique($archivosClave);
    
    // Generar prompt para nuevo chat
    $prompt = "Estoy desarrollando el Sistema ERP KEYDAN para gestión de cotizaciones, ventas, producción e inventario. ";
    $prompt .= "Actualmente el proyecto está al {$progreso['general']}% de avance, trabajando en la fase {$faseActual} ({$porcentajeFaseActual}% completada). ";
    
    if ($selectedModule) {
        $prompt .= "Estoy trabajando específicamente en el módulo: {$selectedModule}. ";
        if (isset($progreso['archivos'][$selectedModule])) {
            $missingFiles = array_filter($progreso['archivos'][$selectedModule]['detalle'], function($info) {
                return !$info['existe'];
            });
            if (!empty($missingFiles)) {
                $prompt .= "Faltan los siguientes archivos en este módulo: " . implode(", ", array_keys($missingFiles)) . ". ";
            }
        }
    } elseif ($selectedTask) {
        if (strpos($selectedTask, 'tabla_') === 0) {
            $tabla = str_replace('tabla_', '', $selectedTask);
            $prompt .= "Estoy trabajando en la creación de la tabla: {$tabla}. ";
        } elseif (strpos($selectedTask, 'archivo_') === 0) {
            $archivo = str_replace('archivo_', '', $selectedTask);
            $prompt .= "Estoy trabajando en la creación del archivo: {$archivo}. ";
        }
    } else {
        if (!empty($proximasTablas)) {
            $prompt .= "Las próximas tablas a implementar son: " . implode(", ", array_slice($proximasTablas, 0, 3));
            if (count($proximasTablas) > 3) {
                $prompt .= " y otras " . (count($proximasTablas) - 3) . " tablas más. ";
            } else {
                $prompt .= ". ";
            }
        }
        
        if (!empty($modulosEnProgreso)) {
            $prompt .= "Estoy trabajando en los módulos: " . implode(", ", $modulosEnProgreso) . ". ";
        }
    }
    
    $prompt .= "He adjuntado los archivos clave de la base de datos y del código. ";
    $prompt .= "Necesito ayuda para continuar con el desarrollo de las funcionalidades pendientes";
    if ($selectedModule) {
        $prompt .= " en el módulo {$selectedModule}.";
    } elseif ($selectedTask) {
        $prompt .= " relacionadas con " . (strpos($selectedTask, 'tabla_') === 0 ? "la tabla " . str_replace('tabla_', '', $selectedTask) : "el archivo " . str_replace('archivo_', '', $selectedTask)) . ".";
    } else {
        $prompt .= " en la fase actual.";
    }
    
    return [
        'fase_actual' => $faseActual,
        'porcentaje_fase' => $porcentajeFaseActual,
        'siguiente_fase' => $siguienteFase,
        'proximas_tablas' => $proximasTablas,
        'modulos_en_progreso' => $modulosEnProgreso,
        'archivos_clave' => $archivosClave,
        'prompt' => $prompt
    ];
}

/**
 * Define recomendaciones de mejora para el sistema
 * @return array Recomendaciones de mejora
 */
function definirRecomendacionesMejora() {
    return [
        [
            'categoria' => 'seguridad',
            'titulo' => 'Implementar sistema de roles y permisos',
            'descripcion' => 'Actualmente el sistema maneja usuarios pero sin un control granular de permisos. Se recomienda implementar un sistema de roles y permisos para controlar el acceso a las diferentes funcionalidades.',
            'complejidad' => 'media',
            'prioridad' => 'alta',
            'pasos' => [
                'Crear tabla de roles en la base de datos',
                'Crear tabla de permisos para asignar a roles',
                'Modificar la lógica de autenticación para verificar permisos',
                'Actualizar las páginas para mostrar/ocultar opciones según permisos'
            ],
            'beneficios' => 'Mayor seguridad, control de acceso granular, auditoría de acciones por rol'
        ],
        [
            'categoria' => 'rendimiento',
            'titulo' => 'Optimizar consultas a la base de datos',
            'descripcion' => 'Algunas consultas SQL podrían optimizarse para mejorar el rendimiento general del sistema, especialmente en los listados y reportes que manejan gran cantidad de datos.',
            'complejidad' => 'media',
            'prioridad' => 'media',
            'pasos' => [
                'Analizar las consultas actuales usando EXPLAIN',
                'Crear índices adicionales en tablas críticas',
                'Implementar paginación en listados grandes',
                'Limitar las columnas recuperadas (evitar SELECT *)'
            ],
            'beneficios' => 'Mejor tiempo de respuesta, menor carga en el servidor, mejor experiencia de usuario'
        ],
        [
            'categoria' => 'arquitectura',
            'titulo' => 'Migrar a arquitectura MVC',
            'descripcion' => 'El sistema actual usa un enfoque tradicional de procesamiento por páginas. Migrar a una arquitectura MVC mejoraría la organización, mantenibilidad y escalabilidad del código.',
            'complejidad' => 'alta',
            'prioridad' => 'baja',
            'pasos' => [
                'Diseñar la estructura de carpetas para modelos, vistas y controladores',
                'Crear clases modelo para entidades principales (Cliente, Cotización, etc.)',
                'Refactorizar la lógica de negocio en controladores',
                'Separar las vistas (HTML) de la lógica'
            ],
            'beneficios' => 'Código más organizado, más fácil de mantener, mejor separación de responsabilidades'
        ],
        [
            'categoria' => 'experiencia',
            'titulo' => 'Mejorar interfaz de usuario con AJAX',
            'descripcion' => 'Implementar AJAX en formularios y listados para evitar recargas completas de página y ofrecer una experiencia más fluida al usuario.',
            'complejidad' => 'media',
            'prioridad' => 'media',
            'pasos' => [
                'Crear endpoints API para las operaciones principales',
                'Modificar formularios para enviar datos vía AJAX',
                'Implementar actualizaciones parciales en listados',
                'Añadir efectos visuales de carga y confirmación'
            ],
            'beneficios' => 'Mejor experiencia de usuario, menor tráfico de red, operaciones más rápidas'
        ],
        [
            'categoria' => 'funcionalidad',
            'titulo' => 'Implementar sistema de notificaciones',
            'descripcion' => 'Crear un sistema de notificaciones en tiempo real para alertar a los usuarios sobre eventos importantes como aprobaciones, nuevos pedidos, etc.',
            'complejidad' => 'media',
            'prioridad' => 'alta',
            'pasos' => [
                'Crear tabla de notificaciones en la base de datos',
                'Desarrollar lógica para generar notificaciones en eventos clave',
                'Implementar sistema de visualización en el header',
                'Añadir opción de notificaciones por correo'
            ],
            'beneficios' => 'Mejor comunicación interna, menos errores por falta de información, procesos más ágiles'
        ]
    ];
}

// Ejecutar análisis del sistema
try {
    // Analizar base de datos
    $estructuraDB = analizarBaseDatos($conn);
    if (isset($estructuraDB['error']) && $estructuraDB['error']) {
        die("Error al analizar la base de datos: " . $estructuraDB['mensaje']);
    }
    
    // Analizar archivos
    $estructuraArchivos = analizarArchivos();
    
    // Analizar progreso
    $progreso = analizarProgreso($estructuraDB, $estructuraArchivos);
    
    // Obtener dependencias
    $dependencias = definirDependencias();
    
    // Obtener recomendaciones
    $recomendaciones = definirRecomendacionesMejora();
    
    // Generar instrucciones de continuidad
    $continuidad = generarContinuidad($progreso, $estructuraDB);
    
    // Modo: HTML o JSON (para integraciones con otros sistemas)
    $modo = isset($_GET['formato']) && $_GET['formato'] === 'json' ? 'json' : 'html';
    
    if ($modo === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'progreso' => $progreso,
            'continuidad' => $continuidad,
            'estructura_db' => $estructuraDB,
            'dependencias' => $dependencias,
            'recomendaciones' => $recomendaciones,
            'estructura_archivos' => $estructuraArchivos
        ]);
        exit;
    }
    
    // Generar reporte HTML
    echo getHeader(TITULO_SISTEMA . ' - Estado del Proyecto');
    
    // Dashboard section
    echo '<section id="dashboard" class="content-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-tachometer-alt mr-2"></i> Dashboard</h2>
                <div>
                    <span class="badge badge-pill badge-primary p-2">Actualizado: ' . date('d/m/Y H:i:s') . '</span>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Progreso General</h5>
                            <div class="display-4 mb-3">' . $progreso['general'] . '%</div>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: ' . $progreso['general'] . '%"></div>
                            </div>
                            <p class="card-text text-muted">Fase actual: ' . htmlspecialchars($continuidad['fase_actual']) . '</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Tablas en BD</h5>
                            <div class="row">
                                <div class="col-6 text-center">
                                    <h3>' . count($estructuraDB['tablas']) . '</h3>
                                    <p class="text-muted">Implementadas</p>
                                </div>
                                <div class="col-6 text-center">
                                    <h3>' . (array_sum(array_column($progreso['tablas'], 'total')) - array_sum(array_column($progreso['tablas'], 'creadas'))) . '</h3>
                                    <p class="text-muted">Pendientes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Archivos PHP</h5>
                            <div class="row">
                                <div class="col-6 text-center">
                                    <h3>' . (isset($estructuraArchivos['archivos']['php']) ? count($estructuraArchivos['archivos']['php']) : 0) . '</h3>
                                    <p class="text-muted">Implementados</p>
                                </div>
                                <div class="col-6 text-center">
                                    <h3>';
    
    // Calcular archivos pendientes
    $totalArchivosEsperados = array_sum(array_column($progreso['archivos'], 'total'));
    $totalArchivosCreados = array_sum(array_column($progreso['archivos'], 'creados'));
    
    echo $totalArchivosEsperados - $totalArchivosCreados;
    
    echo '</h3>
                                    <p class="text-muted">Pendientes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Progreso por Fases</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Fase</th>
                                            <th>Estado</th>
                                            <th>Tablas</th>
                                            <th>Progreso</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
    
    foreach ($progreso['tablas'] as $fase => $datos) {
        $estado = '';
        $badgeClass = '';
        
        if ($datos['porcentaje'] == 100)
                {
            $estado = 'Completado';
            $badgeClass = 'badge-success';
        } elseif ($datos['porcentaje'] > 0) {
            $estado = 'En progreso';
            $badgeClass = 'badge-warning';
        } else {
            $estado = 'Pendiente';
            $badgeClass = 'badge-secondary';
        }
        
        echo '<tr>
                <td>' . htmlspecialchars($fase) . '</td>
                <td><span class="badge ' . $badgeClass . ' badge-status">' . $estado . '</span></td>
                <td>' . $datos['creadas'] . ' / ' . $datos['total'] . '</td>
                <td>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: ' . $datos['porcentaje'] . '%;" 
                             aria-valuenow="' . $datos['porcentaje'] . '" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </td>
              </tr>';
    }
    
    echo '                      </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Próximos Pasos</h5>
                        </div>
                        <div class="card-body p-0">
                            <form method="POST" action="">
                                <div class="list-group list-group-flush">';
    
    // Mostrar tablas pendientes prioritarias
    $addedItems = 0;
    $tablasCompletadasManually = isset($_SESSION['tablas_completadas']) ? $_SESSION['tablas_completadas'] : [];
    $archivosCompletadosManually = isset($_SESSION['archivos_completados']) ? $_SESSION['archivos_completados'] : [];
    
    if (!empty($continuidad['proximas_tablas'])) {
        foreach (array_slice($continuidad['proximas_tablas'], 0, 3) as $index => $tabla) {
            if (isset($progreso['tablas'][$continuidad['fase_actual']]['detalle'][$tabla]) && !$progreso['tablas'][$continuidad['fase_actual']]['detalle'][$tabla]['existe'] && !in_array($tabla, $tablasCompletadasManually)) {
                echo '<div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                <input type="checkbox" name="complete_tabla[]" value="' . htmlspecialchars($tabla) . '" class="mr-2">
                                <i class="fas fa-database text-primary mr-2"></i> Crear tabla <strong>' . htmlspecialchars($tabla) . '</strong>
                            </h6>
                            <small class="text-muted">Fase ' . substr($continuidad['fase_actual'], -1) . '</small>
                        </div>
                        <p class="mb-1 small text-muted">Prioridad: ' . ($index == 0 ? 'Alta' : ($index == 1 ? 'Media' : 'Normal')) . '</p>
                      </div>';
                $addedItems++;
            }
        }
    }
    
    // Mostrar archivos pendientes prioritarios
    $archivos_pendientes = [];
    foreach ($progreso['archivos'] as $modulo => $datos) {
        foreach ($datos['detalle'] as $archivo => $info) {
            if (!$info['existe'] && !in_array($archivo, $archivosCompletadosManually)) {
                $archivos_pendientes[] = [
                    'nombre' => $archivo,
                    'modulo' => $modulo
                ];
                if (count($archivos_pendientes) + $addedItems >= 3) {
                    break 2;
                }
            }
        }
    }
    
    foreach ($archivos_pendientes as $index => $archivo) {
        if ($addedItems < 3) {
            echo '<div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">
                            <input type="checkbox" name="complete_archivo[]" value="' . htmlspecialchars($archivo['nombre']) . '" class="mr-2">
                            <i class="fas fa-file-code text-warning mr-2"></i> Crear archivo <strong>' . htmlspecialchars($archivo['nombre']) . '</strong>
                        </h6>
                        <small class="text-muted">Módulo ' . htmlspecialchars($archivo['modulo']) . '</small>
                    </div>
                    <p class="mb-1 small text-muted">Implementación necesaria para completar el módulo</p>
                  </div>';
            $addedItems++;
        }
    }
    
    if ($addedItems == 0) {
        echo '<div class="list-group-item">
                <p class="text-success mb-0"><i class="fas fa-check-circle mr-2"></i> No hay tareas pendientes inmediatas.</p>
              </div>';
    }
    
    echo '              </div>
                            <div class="p-3">
                                <button type="submit" class="btn btn-primary btn-sm">Marcar como Completado</button>
                            </div>
                        </form>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Atención Requerida
                            </h5>
                        </div>
                        <div class="card-body">';
    
    // Mostrar alertas específicas según el estado del sistema
    $alertas = [];
    
    // Alerta 1: Faltan tablas necesarias para módulos en progreso
    foreach ($dependencias as $id => $modulo) {
        if ($modulo['estado'] == 'en_progreso') {
            foreach ($modulo['dependencias'] as $dep) {
                if ($dep['tipo'] == 'tabla' && $dep['obligatorio'] && !in_array($dep['nombre'], $estructuraDB['tablas']) && !in_array($dep['nombre'], $tablasCompletadasManually)) {
                    $alertas[] = [
                        'texto' => 'La tabla <strong>' . htmlspecialchars($dep['nombre']) . '</strong> es necesaria para el módulo ' . htmlspecialchars($modulo['nombre']),
                        'icono' => 'database',
                        'severidad' => 'alta'
                    ];
                    break;
                }
            }
        }
    }
    
    // Alerta 2: Configuración de seguridad
    if (!in_array('usuarios', $estructuraDB['tablas']) && !in_array('usuarios', $tablasCompletadasManually)) {
        $alertas[] = [
            'texto' => 'Se recomienda implementar el sistema de usuarios y autenticación',
            'icono' => 'user-shield',
            'severidad' => 'media'
        ];
    }
    
    // Mostrar alertas
    if (empty($alertas)) {
        echo '<p class="text-success mb-0"><i class="fas fa-check-circle mr-2"></i> No hay alertas críticas en este momento.</p>';
    } else {
        echo '<ul class="list-unstyled mb-0">';
        foreach ($alertas as $alerta) {
            $claseAlerta = $alerta['severidad'] == 'alta' ? 'text-danger' : 'text-warning';
            echo '<li class="mb-2 ' . $claseAlerta . '">
                    <i class="fas fa-' . $alerta['icono'] . ' mr-2"></i> ' . $alerta['texto'] . '
                  </li>';
        }
        echo '</ul>';
    }
    
    echo '          </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Módulos del Sistema</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">';
    
    // Mostrar tarjetas de módulos
    foreach ($dependencias as $id => $modulo) {
        $claseBorder = '';
        $porcentaje = 0;
        $estadoTexto = '';
        
        switch ($modulo['estado']) {
            case 'implementado':
                $claseBorder = 'border-success';
                $porcentaje = 100;
                $estadoTexto = '<span class="badge badge-success badge-status">Implementado</span>';
                break;
            case 'en_progreso':
                $claseBorder = 'border-warning';
                $porcentaje = 50;
                $estadoTexto = '<span class="badge badge-warning badge-status">En progreso</span>';
                break;
            default:
                $claseBorder = 'border-secondary';
                $porcentaje = 0;
                $estadoTexto = '<span class="badge badge-secondary badge-status">Pendiente</span>';
                break;
        }
        
        echo '<div class="col-md-4 mb-4">
                <div class="card ' . $claseBorder . ' h-100">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="' . $modulo['icono'] . ' mr-2"></i> ' . htmlspecialchars($modulo['nombre']) . '</h5>
                        ' . $estadoTexto . '
                    </div>
                    <div class="card-body">
                        <p class="card-text">' . htmlspecialchars($modulo['descripcion']) . '</p>
                        <div class="progress mb-3">
                            <div class="progress-bar" role="progressbar" style="width: ' . $porcentaje . '%;" 
                                 aria-valuenow="' . $porcentaje . '" aria-valuemin="0" aria-valuemax="100">
                                ' . $porcentaje . '%
                            </div>
                        </div>
                        <h6 class="mt-3">Dependencias:</h6>
                        <ul class="list-group list-group-flush">';
        
        // Mostrar dependencias
        $mostradas = 0;
        foreach ($modulo['dependencias'] as $dep) {
            if ($mostradas >= 3) {
                echo '<li class="list-group-item px-0 py-2 border-0">
                        <small class="text-muted">Y ' . (count($modulo['dependencias']) - 3) . ' dependencias más...</small>
                      </li>';
                break;
            }
            
            $iconoDep = '';
            switch ($dep['tipo']) {
                case 'tabla':
                    $iconoDep = 'database';
                    break;
                case 'archivo':
                    $iconoDep = 'file-code';
                    break;
                case 'modulo':
                    $iconoDep = 'puzzle-piece';
                    break;
            }
            
            $estadoDep = false;
            if ($dep['tipo'] == 'tabla') {
                $estadoDep = in_array($dep['nombre'], $estructuraDB['tablas']) || in_array($dep['nombre'], $tablasCompletadasManually);
            } elseif ($dep['tipo'] == 'archivo') {
                $estadoDep = in_array($dep['nombre'], array_map('basename', array_column($estructuraArchivos['archivos']['php'] ?? [], 'ruta'))) || in_array($dep['nombre'], $archivosCompletadosManually);
            } elseif ($dep['tipo'] == 'modulo') {
                $estadoDep = isset($dependencias[$dep['nombre']]) && $dependencias[$dep['nombre']]['estado'] == 'implementado';
            }
            
            $claseEstadoDep = $estadoDep ? 'text-success' : ($dep['obligatorio'] ? 'text-danger' : 'text-warning');
            $iconoEstadoDep = $estadoDep ? 'check-circle' : 'exclamation-circle';
            
            echo '<li class="list-group-item px-0 py-2 border-0">
                    <i class="fas fa-' . $iconoDep . ' mr-2 text-muted"></i> ' . htmlspecialchars($dep['nombre']) . '
                    <i class="fas fa-' . $iconoEstadoDep . ' float-right ' . $claseEstadoDep . '"></i>
                  </li>';
            
            $mostradas++;
        }
        
        echo '      </ul>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="#dependencias-' . htmlspecialchars($id) . '" class="btn btn-sm btn-outline-primary btn-details">
                            <i class="fas fa-info-circle mr-1"></i> Detalles
                        </a>
                    </div>
                </div>
            </div>';
    }
    
    echo '              </div>
                        </div>
                    </div>
                </div>
            </div>
          </section>';
    
    // Continuidad section
    echo '<section id="continuidad" class="content-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-sync-alt mr-2"></i> Continuidad del Proyecto</h2>
                <div>
                    <span class="badge badge-pill badge-info p-2">Nuevo Chat</span>
                </div>
            </div>
            
            <div class="alert alert-info alert-custom sticky-alert mb-4">
                <div class="d-flex">
                    <div class="alert-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h5 class="alert-heading">Instrucciones para continuar el desarrollo</h5>
                        <p class="mb-0">Esta sección te ayudará a mantener la continuidad del proyecto cuando necesites iniciar un nuevo chat con la IA. Sigue estas instrucciones para asegurar que el desarrollo continúe sin pérdida de contexto.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-import mr-2"></i> Archivos para compartir</h5>
                        </div>
                        <div class="card-body">
                            <p>Comparte estos archivos en el nuevo chat para mantener el contexto del desarrollo:</p>
                            
                            <div class="context-files">
                                <h6 class="mb-3">Archivos esenciales:</h6>
                                <div id="essential-files">
                                    <ul class="mb-3">';
    
    // Mostrar archivos clave
    foreach ($continuidad['archivos_clave'] as $archivo) {
        echo '<li><i class="fas fa-file-code mr-2 text-primary"></i> ' . htmlspecialchars($archivo) . '</li>';
    }
    
    echo '                  </ul>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-primary copy-files">
                                        <i class="fas fa-clipboard-list mr-1"></i> Copiar lista de archivos
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6>Tablas de la base de datos:</h6>
                                <p class="text-muted small">Si estás trabajando en un módulo específico, considera compartir la estructura de estas tablas relacionadas:</p>
                                
                                <div class="row">';
    
    // Tablas por categoría
    $categorias = [
        'Clientes' => ['clientes'],
        'Cotizaciones' => ['cotizaciones', 'cotizacion_detalles'],
        'Proformas' => ['proformas', 'proforma_detalles'],
        'Órdenes' => ['ordenes_venta', 'orden_detalles'],
        'Sistema' => ['usuarios']
    ];
    
    foreach ($categorias as $categoria => $tablas) {
        echo '<div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header py-2 px-3 bg-light">
                        <h6 class="mb-0 small">' . htmlspecialchars($categoria) . '</h6>
                    </div>
                    <div class="card-body py-2 px-3">
                        <ul class="list-unstyled mb-0 small">';
        
        foreach ($tablas as $tabla) {
            $existe = in_array($tabla, $estructuraDB['tablas']) || in_array($tabla, $tablasCompletadasManually);
            $clase = $existe ? 'text-success' : 'text-muted';
            $icono = $existe ? 'check-circle' : 'circle';
            
            echo '<li class="' . $clase . '">
                    <i class="fas fa-' . $icono . ' mr-2"></i> ' . htmlspecialchars($tabla) . '
                  </li>';
        }
        
        echo '      </ul>
                    </div>
                </div>
              </div>';
    }
    
    echo '          </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-keyboard mr-2"></i> Prompt para nuevo chat</h5>
                    </div>
                    <div class="card-body">
                        <p>Copia y pega el siguiente texto al iniciar un nuevo chat con la IA:</p>
                        
                        <div class="form-group">
                            <label for="select-focus">Seleccionar enfoque para el prompt:</label>
                            <select class="form-control mb-3" id="select-focus">
                                <option value="">-- Seleccionar enfoque --</option>
                                <optgroup label="Módulos">
                                    <option value="">General (Fase Actual)</option>';
    
    foreach ($progreso['archivos'] as $modulo => $datos) {
        echo '<option value="modulo_' . htmlspecialchars($modulo) . '">' . htmlspecialchars($modulo) . '</option>';
    }
    
    echo '                  </optgroup>
                                <optgroup label="Tablas Pendientes">';
    
    if (!empty($continuidad['proximas_tablas'])) {
        foreach ($continuidad['proximas_tablas'] as $tabla) {
            if (isset($progreso['tablas'][$continuidad['fase_actual']]['detalle'][$tabla]) && !$progreso['tablas'][$continuidad['fase_actual']]['detalle'][$tabla]['existe'] && !in_array($tabla, $tablasCompletadasManually)) {
                echo '<option value="tabla_' . htmlspecialchars($tabla) . '">Tabla: ' . htmlspecialchars($tabla) . '</option>';
            }
        }
    }
    
    echo '                  </optgroup>
                                <optgroup label="Archivos Pendientes">';
    
    foreach ($progreso['archivos'] as $modulo => $datos) {
        foreach ($datos['detalle'] as $archivo => $info) {
            if (!$info['existe'] && !in_array($archivo, $archivosCompletadosManually)) {
                echo '<option value="archivo_' . htmlspecialchars($archivo) . '">Archivo: ' . htmlspecialchars($archivo) . ' (Módulo: ' . htmlspecialchars($modulo) . ')</option>';
            }
        }
    }
    
    echo '                  </optgroup>
                            </select>
                            <textarea id="prompt-textarea" class="form-control code-block">' . htmlspecialchars($continuidad['prompt']) . '</textarea>
                        </div>
                        
                        <button id="copy-prompt" class="btn btn-primary">
                            <i class="fas fa-copy mr-1"></i> Copiar
                        </button>
                        
                        <div class="mt-4">
                            <h6>Elementos a personalizar en el prompt:</h6>
                            <ul class="text-muted">
                                <li>Actualiza el porcentaje de avance si ha cambiado</li>
                                <li>Especifica el módulo o tarea concreta en la que estás trabajando</li>
                                <li>Menciona desafíos o problemas específicos que estés enfrentando</li>
                                <li>Incluye detalles sobre funcionalidades o características específicas que quieras implementar</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tasks mr-2"></i> Estado actual</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Fase actual:</span>
                                <strong>' . htmlspecialchars($continuidad['fase_actual']) . '</strong>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Progreso de fase:</span>
                                <strong>' . $continuidad['porcentaje_fase'] . '%</strong>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Siguiente fase:</span>
                                <strong>' . htmlspecialchars($continuidad['siguiente_fase']) . '</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-ol mr-2"></i> Próximas tareas</h5>
                    </div>
                    <div class="card-body">
                        <h6>Tareas prioritarias:</h6>
                        <ul>';
    
    $addedTasks = 0;
    if (!empty($continuidad['proximas_tablas'])) {
        echo '<li>Implementar las siguientes tablas:
                <ul>';
        foreach (array_slice($continuidad['proximas_tablas'], 0, 3) as $tabla) {
            if (isset($progreso['tablas'][$continuidad['fase_actual']]['detalle'][$tabla]) && !$progreso['tablas'][$continuidad['fase_actual']]['detalle'][$tabla]['existe'] && !in_array($tabla, $tablasCompletadasManually)) {
                echo '<li><strong>' . htmlspecialchars($tabla) . '</strong></li>';
                $addedTasks++;
            }
        }
        if (count($continuidad['proximas_tablas']) > 3) {
            echo '<li>Y otras ' . (count($continuidad['proximas_tablas']) - 3) . ' tablas más...</li>';
        }
        echo '</ul>
              </li>';
    }
    
    if (!empty($continuidad['modulos_en_progreso'])) {
        echo '<li>Completar los siguientes módulos:
                <ul>';
        foreach ($continuidad['modulos_en_progreso'] as $modulo) {
            if ($addedTasks < 3) {
                echo '<li><strong>' . htmlspecialchars($modulo) . '</strong></li>';
                $addedTasks++;
            }
        }
        echo '</ul>
              </li>';
    }
    
    if ($addedTasks == 0) {
        echo '<li class="text-success"><i class="fas fa-check-circle mr-2"></i> No hay tareas pendientes inmediatas.</li>';
    }
    
    echo '      </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lightbulb mr-2"></i> Consejos</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success p-3 mb-3">
                            <i class="fas fa-check-circle mr-2"></i> Conserva los ID y nombres de variables para mantener consistencia.
                        </div>
                        <div class="alert alert-success p-3 mb-3">
                            <i class="fas fa-check-circle mr-2"></i> Documenta los cambios realizados para facilitar la continuidad.
                        </div>
                        <div class="alert alert-success p-3">
                            <i class="fas fa-check-circle mr-2"></i> Actualiza esta herramienta de seguimiento después de cada sesión.
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-circle mr-2"></i> Para Completar la Fase ' . htmlspecialchars($continuidad['fase_actual']) . '</h5>
                    </div>
                    <div class="card-body">
                        <h6>Faltan las siguientes tablas:</h6>';
    
    // Mostrar tablas pendientes para la fase actual
    $tablasPendientes = [];
    if (!empty($continuidad['proximas_tablas'])) {
        echo '<ul>';
        foreach ($continuidad['proximas_tablas'] as $tabla) {
            echo '<li>' . htmlspecialchars($tabla) . '</li>';
            $tablasPendientes[] = $tabla;
        }
        echo '</ul>';
    } else {
        echo '<p class="text-success"><i class="fas fa-check-circle mr-2"></i> Todas las tablas de esta fase están completadas.</p>';
    }
    
    echo '          <h6 class="mt-3">Faltan los siguientes archivos:</h6>';
    
    // Mostrar archivos pendientes para los módulos en progreso
    $archivosPendientesFase = [];
    foreach ($continuidad['modulos_en_progreso'] as $modulo) {
        if (isset($progreso['archivos'][$modulo])) {
            foreach ($progreso['archivos'][$modulo]['detalle'] as $archivo => $info) {
                if (!$info['existe'] && !in_array($archivo, $archivosCompletadosManually)) {
                    $archivosPendientesFase[] = ['nombre' => $archivo, 'modulo' => $modulo];
                }
            }
        }
    }
    
    if (!empty($archivosPendientesFase)) {
        echo '<ul>';
        foreach ($archivosPendientesFase as $archivo) {
            echo '<li>' . htmlspecialchars($archivo['nombre']) . ' (Módulo: ' . htmlspecialchars($archivo['modulo']) . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="text-success"><i class="fas fa-check-circle mr-2"></i> Todos los archivos de los módulos en progreso están completados.</p>';
    }
    
    echo '      </div>
                </div>
            </div>
        </div>
    </section>';
    
    // Dependencias section
    echo '<section id="dependencias" class="content-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-project-diagram mr-2"></i> Dependencias y Requisitos</h2>
                <div>
                    <span class="badge badge-pill badge-primary p-2">Componentes del Sistema</span>
                </div>
            </div>
            
            <div class="alert alert-primary alert-custom mb-4">
                <div class="d-flex">
                    <div class="alert-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h5 class="alert-heading">¿Qué necesitas implementar?</h5>
                        <p class="mb-0">Esta sección muestra los componentes necesarios para cada módulo del sistema. Consulta esta guía antes de comenzar a trabajar en un nuevo módulo para asegurarte                         de tener todos los prerrequisitos.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">';
    
    // Mostrar tarjetas para cada módulo
    foreach ($dependencias as $id => $modulo) {
        echo '<div class="col-md-6 mb-4">
                <div class="card" id="dependencias-' . htmlspecialchars($id) . '">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="' . $modulo['icono'] . ' mr-2"></i> ' . htmlspecialchars($modulo['nombre']) . '
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">' . htmlspecialchars($modulo['descripcion']) . '</p>
                        
                        <div class="alert alert-primary p-3 mb-4">
                            <strong><i class="fas fa-info-circle mr-2"></i> Instrucciones:</strong><br>
                            ' . htmlspecialchars($modulo['instrucciones']) . '
                        </div>
                        
                        <h6 class="mb-3">Archivos clave:</h6>
                        <div class="dependency-files mb-4">
                            <ul class="mb-0">';
        
        foreach ($modulo['archivos_clave'] as $archivo) {
            echo '<li>' . htmlspecialchars($archivo) . '</li>';
        }
        
        echo '      </ul>
                        </div>
                        
                        <h6 class="border-bottom pb-2 mb-3">Dependencias requeridas:</h6>';
        
        // Agrupar dependencias por tipo
        $depPorTipo = [];
        foreach ($modulo['dependencias'] as $dep) {
            if (!isset($depPorTipo[$dep['tipo']])) {
                $depPorTipo[$dep['tipo']] = [];
            }
            $depPorTipo[$dep['tipo']][] = $dep;
        }
        
        // Mostrar dependencias agrupadas
        foreach ($depPorTipo as $tipo => $deps) {
            $iconoTipo = '';
            $tituloTipo = '';
            
            switch ($tipo) {
                case 'tabla':
                    $iconoTipo = 'database';
                    $tituloTipo = 'Tablas de BD';
                    break;
                case 'archivo':
                    $iconoTipo = 'file-code';
                    $tituloTipo = 'Archivos PHP';
                    break;
                case 'modulo':
                    $iconoTipo = 'puzzle-piece';
                    $tituloTipo = 'Módulos';
                    break;
            }
            
            echo '<div class="dependency-item">
                    <div class="dependency-icon">
                        <i class="fas fa-' . $iconoTipo . '"></i>
                    </div>
                    <div class="dependency-content">
                        <div class="dependency-title">
                            <span>' . $tituloTipo . '</span>
                        </div>
                        <ul class="list-group list-group-flush">';
            
            foreach ($deps as $dep) {
                $estadoDep = false;
                if ($dep['tipo'] == 'tabla') {
                    $estadoDep = in_array($dep['nombre'], $estructuraDB['tablas']) || in_array($dep['nombre'], $tablasCompletadasManually);
                } elseif ($dep['tipo'] == 'archivo') {
                    $estadoDep = in_array($dep['nombre'], array_map('basename', array_column($estructuraArchivos['archivos']['php'] ?? [], 'ruta'))) || in_array($dep['nombre'], $archivosCompletadosManually);
                } elseif ($dep['tipo'] == 'modulo') {
                    $estadoDep = isset($dependencias[$dep['nombre']]) && $dependencias[$dep['nombre']]['estado'] == 'implementado';
                }
                
                $badgeTipo = $dep['obligatorio'] ? 'badge-danger' : 'badge-warning';
                $badgeTexto = $dep['obligatorio'] ? 'Obligatorio' : 'Opcional';
                
                $iconoEstado = $estadoDep ? 'check-circle' : 'times-circle';
                $claseEstado = $estadoDep ? 'text-success' : 'text-muted';
                
                echo '<li class="list-group-item border-0 px-0 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-' . $iconoEstado . ' mr-2 ' . $claseEstado . '"></i>
                                ' . htmlspecialchars($dep['nombre']) . '
                            </div>
                            <span class="badge ' . $badgeTipo . ' badge-pill">' . $badgeTexto . '</span>
                        </div>
                      </li>';
            }
            
            echo '  </ul>
                    </div>
                  </div>';
        }
        
        echo '  </div>
                <div class="card-footer bg-transparent">
                    <span class="text-muted small">
                        <i class="fas fa-info-circle mr-1"></i> Completa estas dependencias antes de implementar este módulo
                    </span>
                </div>
              </div>
            </div>';
    }
    
    echo '</div>
        </section>';
    
    // Base de datos section
    echo '<section id="base-datos" class="content-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-database mr-2"></i> Estructura de Base de Datos</h2>
                <div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                            <i class="fas fa-download mr-1"></i> Exportar
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" id="export-sql">SQL Create Tables</a>
                            <a class="dropdown-item" href="#" id="export-diagram">Diagrama ER</a>
                            <a class="dropdown-item" href="#" id="export-json">JSON</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#tab-tablas" role="tab">
                        <i class="fas fa-table mr-1"></i> Tablas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-relaciones" role="tab">
                        <i class="fas fa-project-diagram mr-1"></i> Relaciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-sql" role="tab">
                        <i class="fas fa-code mr-1"></i> SQL
                    </a>
                </li>
            </ul>
            
            <div class="tab-content tab-content-custom">
                <div class="tab-pane fade show active" id="tab-tablas" role="tabpanel">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <input type="text" class="form-control" placeholder="Buscar tabla..." id="search-table">
                        </div>
                    </div>
                    
                    <div class="accordion" id="accordionDB">';
    
    $contador = 1;
    foreach ($estructuraDB['estructura'] as $tabla => $datos) {
        echo '<div class="card mb-2">
                <div class="card-header p-0" id="heading' . $contador . '">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left p-3" type="button" data-toggle="collapse" 
                                data-target="#collapse' . $contador . '" aria-expanded="false" aria-controls="collapse' . $contador . '">
                            <i class="fas fa-table mr-2 text-primary"></i> ' . htmlspecialchars($tabla) . '
                            <span class="badge badge-info badge-pill float-right">' . count($datos['columnas']) . ' columnas</span>
                        </button>
                    </h2>
                </div>
                <div id="collapse' . $contador . '" class="collapse" aria-labelledby="heading' . $contador . '" data-parent="#accordionDB">
                    <div class="card-body">
                        <h6 class="mb-3">Estructura de la tabla:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover table-structure">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Campo</th>
                                        <th>Tipo</th>
                                        <th>Nulo</th>
                                        <th>Clave</th>
                                        <th>Default</th>
                                        <th>Extra</th>
                                    </tr>
                                </thead>
                                <tbody>';
        
        foreach ($datos['columnas'] as $columna) {
            $clasePrimaria = $columna['Key'] == 'PRI' ? 'table-primary' : '';
            
            echo '<tr class="' . $clasePrimaria . '">
                    <td>' . htmlspecialchars($columna['Field']) . '</td>
                    <td>' . htmlspecialchars($columna['Type']) . '</td>
                    <td>' . htmlspecialchars($columna['Null']) . '</td>
                    <td>' . htmlspecialchars($columna['Key']) . '</td>
                    <td>' . ($columna['Default'] !== null ? htmlspecialchars($columna['Default']) : '<em>NULL</em>') . '</td>
                    <td>' . htmlspecialchars($columna['Extra']) . '</td>
                  </tr>';
        }
        
        echo '      </tbody>
                        </table>
                    </div>';
        
        if (!empty($datos['claves_foraneas'])) {
            echo '<h6 class="mt-4 mb-3">Relaciones:</h6>
                  <div class="table-responsive">
                      <table class="table table-sm table-bordered table-hover table-structure">
                          <thead class="thead-light">
                              <tr>
                                  <th>Columna</th>
                                  <th>Referencia</th>
                                  <th>Nombre Restricción</th>
                              </tr>
                          </thead>
                          <tbody>';
            
            foreach ($datos['claves_foraneas'] as $relacion) {
                echo '<tr>
                        <td>' . htmlspecialchars($relacion['COLUMN_NAME']) . '</td>
                        <td>' . htmlspecialchars($relacion['REFERENCED_TABLE_NAME']) . '.' . htmlspecialchars($relacion['REFERENCED_COLUMN_NAME']) . '</td>
                        <td>' . htmlspecialchars($relacion['CONSTRAINT_NAME']) . '</td>
                      </tr>';
            }
            
            echo '      </tbody>
                      </table>
                  </div>';
        }
        
        echo '  </div>
              </div>
            </div>';
        
        $contador++;
    }
    
    echo '  </div>
          </div>
          
          <div class="tab-pane fade" id="tab-relaciones" role="tabpanel">
              <div class="alert alert-info mb-4">
                  <i class="fas fa-info-circle mr-2"></i> 
                  Este diagrama muestra las relaciones entre las tablas de la base de datos.
              </div>
              
              <div class="text-center">
                  <img src="https://via.placeholder.com/800x600" alt="Diagrama ER" class="img-fluid">
              </div>
          </div>
          
          <div class="tab-pane fade" id="tab-sql" role="tabpanel">
              <div class="mb-3">
                  <div class="form-group">
                      <label for="select-sql-table">Seleccionar tabla:</label>
                      <select class="form-control" id="select-sql-table">
                          <option value="">-- Todas las tablas --</option>';
    
    foreach ($estructuraDB['tablas'] as $tabla) {
        echo '<option value="' . htmlspecialchars($tabla) . '">' . htmlspecialchars($tabla) . '</option>';
    }
    
    echo '      </select>
                  </div>
              </div>
              
              <div class="code-block">
                  -- SQL para crear la estructura de la base de datos
                  
                  -- Tabla: clientes
                  CREATE TABLE `clientes` (
                    `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
                    `razon_social` varchar(100) NOT NULL,
                    `ruc` varchar(11) NOT NULL,
                    `direccion` text,
                    `telefono` varchar(20) DEFAULT NULL,
                    `email` varchar(100) DEFAULT NULL,
                    `contacto_nombre` varchar(100) DEFAULT NULL,
                    `contacto_cargo` varchar(50) DEFAULT NULL,
                    `estado` enum(\'activo\',\'inactivo\') DEFAULT \'activo\',
                    `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_cliente`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                  
                  -- Más tablas...
              </div>
          </div>
      </div>
    </section>';
    // Sección de Relaciones entre Archivos PHP
echo '<section id="relaciones-archivos" class="content-section" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-file-code mr-2"></i> Relaciones entre Archivos PHP</h2>
            <div>
                <span class="badge badge-pill badge-primary p-2">Análisis de Dependencias de Archivos</span>
            </div>
        </div>

        <div class="alert alert-info alert-custom mb-4">
            <div class="d-flex">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <h5 class="alert-heading">Relaciones entre Archivos PHP</h5>
                    <p class="mb-0">Muestra cómo los archivos PHP dependen unos de otros dentro del sistema.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-code mr-2"></i> Dependencias entre Archivos PHP</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">';

$relacionesArchivosPHP = [];
if (!empty($dependencias)) {
    // Construir un mapa de dependencias entre archivos
    foreach ($dependencias as $moduloId => $modulo) {
        $archivosClave = $modulo['archivos_clave'];
        $archivosDependientes = [];
        
        // Buscar dependencias de tipo 'archivo' en el módulo actual
        foreach ($modulo['dependencias'] as $dep) {
            if ($dep['tipo'] === 'archivo') {
                $archivosDependientes[] = $dep['nombre'];
            }
        }
        
        // Buscar dependencias de tipo 'modulo' y agregar sus archivos clave
        foreach ($modulo['dependencias'] as $dep) {
            if ($dep['tipo'] === 'modulo' && isset($dependencias[$dep['nombre']])) {
                $moduloDependiente = $dependencias[$dep['nombre']];
                $archivosDependientes = array_merge($archivosDependientes, $moduloDependiente['archivos_clave']);
            }
        }
        
        // Asignar las dependencias a cada archivo clave del módulo
        foreach ($archivosClave as $archivo) {
            $relacionesArchivosPHP[$archivo] = [
                'depende_de' => array_unique($archivosDependientes),
                'modulo' => $modulo['nombre']
            ];
        }
    }

    if (!empty($relacionesArchivosPHP)) {
        echo '<table class="table table-hover table-structure">
                <thead class="thead-light">
                    <tr>
                        <th>Archivo PHP</th>
                        <th>Depende de</th>
                        <th>Módulo</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($relacionesArchivosPHP as $archivo => $info) {
            echo '<tr>
                    <td>' . htmlspecialchars($archivo) . '</td>
                    <td>' . (empty($info['depende_de']) ? 'Ninguno' : implode(', ', array_map('htmlspecialchars', $info['depende_de']))) . '</td>
                    <td>' . htmlspecialchars($info['modulo']) . '</td>
                  </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="text-muted">No se encontraron dependencias entre archivos PHP.</p>';
    }
} else {
    echo '<p class="text-danger">Error: No se encontraron datos de dependencias.</p>';
}

echo '      </div>
            </div>
        </div>
    </section>';
    
    // Sección de Relaciones Archivos y Tablas
    echo '<section id="relaciones-archivos-tablas" class="content-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-link mr-2"></i> Relaciones Archivos y Tablas</h2>
                <div>
                    <span class="badge badge-pill badge-primary p-2">Análisis de Dependencias</span>
                </div>
            </div>

            <div class="alert alert-info alert-custom mb-4">
                <div class="d-flex">
                    <div class="alert-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h5 class="alert-heading">Relaciones del Sistema</h5>
                        <p class="mb-0">Muestra cómo los archivos PHP interactúan con las tablas de la base de datos.</p>
                    </div>
                </div>
            </div>';

    // Relaciones de Archivos PHP
    echo '<div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-code mr-2"></i> Relaciones de Archivos PHP</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">';

    $relacionesArchivos = [];
    if (!empty($dependencias)) {
        foreach ($dependencias as $moduloId => $modulo) {
            foreach ($modulo['archivos_clave'] as $archivo) {
                $tablasRelacionadas = [];
                foreach ($modulo['dependencias'] as $dep) {
                    if ($dep['tipo'] === 'tabla') {
                        $tablasRelacionadas[] = $dep['nombre'];
                    }
                }
                $relacionesArchivos[$archivo] = [
                    'tablas' => array_unique($tablasRelacionadas),
                    'modulo' => $modulo['nombre']
                ];
            }
        }

        if (!empty($relacionesArchivos)) {
            echo '<table class="table table-hover table-structure">
                    <thead class="thead-light">
                        <tr>
                            <th>Archivo PHP</th>
                            <th>Tablas Relacionadas</th>
                            <th>Módulo</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ($relacionesArchivos as $archivo => $info) {
                echo '<tr>
                        <td>' . htmlspecialchars($archivo) . '</td>
                        <td>' . (empty($info['tablas']) ? 'Ninguna' : implode(', ', array_map('htmlspecialchars', $info['tablas']))) . '</td>
                        <td>' . htmlspecialchars($info['modulo']) . '</td>
                      </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="text-muted">No se encontraron relaciones entre archivos PHP y tablas.</p>';
        }
    } else {
        echo '<p class="text-danger">Error: No se encontraron datos de dependencias.</p>';
    }

    echo '      </div>
            </div>
        </div>';

    // Relaciones entre Tablas
    echo '<div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database mr-2"></i> Relaciones entre Tablas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">';

    if (!empty($estructuraDB['estructura'])) {
        echo '<table class="table table-hover table-structure">
                <thead class="thead-light">
                    <tr>
                        <th>Tabla</th>
                        <th>Claves Foráneas</th>
                        <th>Tablas Referenciadas</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($estructuraDB['estructura'] as $tabla => $datos) {
            $clavesForaneas = [];
            $tablasReferenciadas = [];
            foreach ($datos['claves_foraneas'] as $fk) {
                $clavesForaneas[] = $fk['COLUMN_NAME'] . ' → ' . $fk['REFERENCED_TABLE_NAME'] . '.' . $fk['REFERENCED_COLUMN_NAME'];
                $tablasReferenciadas[] = $fk['REFERENCED_TABLE_NAME'];
            }
            echo '<tr>
                    <td>' . htmlspecialchars($tabla) . '</td>
                    <td>' . (empty($clavesForaneas) ? 'Ninguna' : implode(', ', array_map('htmlspecialchars', $clavesForaneas))) . '</td>
                    <td>' . (empty($tablasReferenciadas) ? 'Ninguna' : implode(', ', array_map('htmlspecialchars', array_unique($tablasReferenciadas)))) . '</td>
                  </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="text-danger">Error: No se encontraron datos de la estructura de la base de datos.</p>';
    }

    echo '      </div>
            </div>
        </div>
    </section>';
    
    // Documentación section
    echo '<section id="documentacion" class="content-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-book mr-2"></i> Documentación y Guías</h2>
                <div>
                    <a href="#" class="btn btn-sm btn-outline-primary" id="print-docs">
                        <i class="fas fa-print mr-1"></i> Imprimir
                    </a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <a href="#" class="documentation-link" data-toggle="modal" data-target="#docModal">
                        <div class="card documentation-card">
                            <div class="card-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-book text-primary" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="card-title">Manual de Usuario</h5>
                                <p class="card-text text-muted">Guía completa para usuarios finales del sistema.</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4 mb-4">
                    <a href="#" class="documentation-link" data-toggle="modal" data-target="#docModal">
                        <div class="card documentation-card">
                            <div class="card-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-code text-danger" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="card-title">Documentación Técnica</h5>
                                <p class="card-text text-muted">Especificaciones técnicas y arquitectura del sistema.</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4 mb-4">
                    <a href="#" class="documentation-link" data-toggle="modal" data-target="#docModal">
                        <div class="card documentation-card">
                            <div class="card-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-file-alt text-success" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="card-title">Guía de Desarrollo</h5>
                                <p class="card-text text-muted">Convenciones y buenas prácticas para desarrolladores.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lightbulb mr-2"></i> Recomendaciones de Mejora</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Estas recomendaciones pueden ayudar a mejorar el sistema en términos de seguridad, rendimiento y usabilidad:</p>
                    <div class="row">';
    
    // Mostrar recomendaciones
    foreach ($recomendaciones as $recomendacion) {
        // Filtrar recomendaciones irrelevantes
        if ($recomendacion['titulo'] == 'Implementar sistema de roles y permisos' && (in_array('usuarios', $estructuraDB['tablas']) || in_array('usuarios', $tablasCompletadasManually))) {
            continue; // Skip if 'usuarios' table exists
        }
        
        $colorPrioridad = '';
        switch ($recomendacion['prioridad']) {
            case 'alta':
                $colorPrioridad = 'danger';
                break;
            case 'media':
                $colorPrioridad = 'warning';
                break;
            default:
                $colorPrioridad = 'secondary';
                break;
        }
        
        echo '<div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">' . htmlspecialchars($recomendacion['titulo']) . '</h6>
                    </div>
                    <div class="card-body">
                        <p class="card-text">' . htmlspecialchars($recomendacion['descripcion']) . '</p>
                        <p class="mb-2"><strong>Categoría:</strong> ' . htmlspecialchars($recomendacion['categoria']) . '</p>
                        <p class="mb-2"><strong>Complejidad:</strong> ' . htmlspecialchars($recomendacion['complejidad']) . '</p>
                        <p class="mb-2"><strong>Prioridad:</strong> <span class="badge badge-' . $colorPrioridad . '">' . htmlspecialchars($recomendacion['prioridad']) . '</span></p>
                        <h6 class="mt-3">Pasos:</h6>
                        <ul>';
        
        foreach ($recomendacion['pasos'] as $paso) {
            echo '<li>' . htmlspecialchars($paso) . '</li>';
        }
        
        echo '      </ul>
                        <p><strong>Beneficios:</strong> ' . htmlspecialchars($recomendacion['beneficios']) . '</p>
                    </div>
                </div>
              </div>';
    }
    
    echo '      </div>
                </div>
            </div>
          </section>';
    
    // Close the HTML output with the footer
    echo getFooter();

} catch (Exception $e) {
    // Handle any unexpected errors during execution
    echo '<div class="container mt-5">
            <div class="alert alert-danger">
                <h4 class="alert-heading">Error Fatal</h4>
                <p>Se produjo un error al ejecutar el script: ' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Por favor, revisa los logs del servidor o contacta al administrador.</p>
            </div>
          </div>';
}

?>
                    