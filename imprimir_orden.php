<?php
/**
 * imprimir_orden.php
 * Genera una versión imprimible de la orden de venta
 */
require_once 'config/db.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: ID de orden no proporcionado");
}

$id_orden = $_GET['id'];

try {
    // Obtener datos de la orden
    $stmt = $conn->prepare("SELECT o.*, cl.razon_social as cliente, cl.ruc, cl.direccion, cl.telefono, cl.email,
                           u.nombre as usuario_nombre, u.apellido as usuario_apellido,
                           p.codigo as codigo_proforma, p.id_proforma
                           FROM ordenes_venta o 
                           JOIN clientes cl ON o.id_cliente = cl.id_cliente 
                           JOIN usuarios u ON o.id_usuario = u.id_usuario
                           LEFT JOIN proformas p ON o.id_proforma = p.id_proforma
                           WHERE o.id_orden = :id");
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        die("Error: Orden no encontrada");
    }
    
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles de la orden
    $stmt = $conn->prepare("SELECT od.*, m.nombre as material 
                           FROM orden_detalles od 
                           JOIN materiales m ON od.id_material = m.id_material 
                           WHERE od.id_orden = :id");
    $stmt->bindParam(':id', $id_orden);
    $stmt->execute();
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener datos de la empresa
    $empresa = [
        'nombre' => 'KEYDAN S.A.C.',
        'ruc' => '20603550421',
        'direccion' => 'Calle Los Nogales 250, Santa Anita, Lima',
        'telefono' => '(01) 356-7890',
        'email' => 'ventas@keydansac.com',
        'sitio_web' => 'www.keydansac.com'
    ];
    
} catch(PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// Función para formatear la fecha
function formatearFecha($fecha) {
    $timestamp = strtotime($fecha);
    return date('d/m/Y', $timestamp);
}

// Función para obtener el nombre del estado
function obtenerEstado($estado) {
    switch($estado) {
        case 'pendiente': return 'Pendiente';
        case 'en_produccion': return 'En Producción';
        case 'completada': return 'Completada';
        case 'cancelada': return 'Cancelada';
        default: return ucfirst($estado);
    }
}

// Función para obtener la clase CSS del estado
function obtenerClaseEstado($estado) {
    switch($estado) {
        case 'pendiente': return 'estado-pendiente';
        case 'en_produccion': return 'estado-produccion';
        case 'completada': return 'estado-completada';
        case 'cancelada': return 'estado-cancelada';
        default: return '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Venta <?php echo $orden['codigo']; ?> - KEYDAN S.A.C.</title>
    <style>
        @page {
            margin: 1cm;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }
        
        .company-info {
            text-align: right;
            font-size: 11px;
        }
        
        .document-title {
            text-align: center;
            margin: 20px 0;
            font-size: 18px;
            font-weight: bold;
            color: #3498db;
        }
        
        .order-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .order-info-box {
            width: 48%;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        
        .order-meta {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        
        .meta-item {
            text-align: center;
            width: 25%;
        }
        
        .meta-label {
            font-weight: bold;
            color: #3498db;
            display: block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f8f9fa;
            color: #333;
        }
        
        .text-right {
            text-align: right;
        }
        
        .estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
        }
        
        .estado-pendiente {
            background-color: #f39c12;
        }
        
        .estado-produccion {
            background-color: #3498db;
        }
        
        .estado-completada {
            background-color: #2ecc71;
        }
        
        .estado-cancelada {
            background-color: #e74c3c;
        }
        
        .footer {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 10px;
            text-align: center;
            color: #777;
        }
        
        .total-section {
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
        }
        
        .total-label {
            width: 150px;
            text-align: right;
            padding-right: 10px;
            font-weight: bold;
        }
        
        .total-value {
            width: 100px;
            text-align: right;
        }
        
        .grand-total {
            font-size: 14px;
            font-weight: bold;
        }
        
        .terms {
            margin-top: 30px;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            font-size: 11px;
        }
        
        .terms-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .notes {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-style: italic;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            width: 45%;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            width: 80%;
            margin: 50px auto 10px;
        }
        
        .print-only {
            display: block;
        }
        
        .no-print {
            display: none;
        }
        
        @media screen {
            .print-only {
                display: none;
            }
            
            .no-print {
                display: block;
                margin: 20px 0;
                text-align: center;
            }
            
            .btn-print {
                background-color: #3498db;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .btn-back {
                background-color: #7f8c8d;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                margin-right: 10px;
            }
        }
        
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><?php echo $empresa['nombre']; ?></div>
            <div class="company-info">
                <div>RUC: <?php echo $empresa['ruc']; ?></div>
                <div><?php echo $empresa['direccion']; ?></div>
                <div>Teléfono: <?php echo $empresa['telefono']; ?></div>
                <div>Email: <?php echo $empresa['email']; ?></div>
                <div>Web: <?php echo $empresa['sitio_web']; ?></div>
            </div>
        </div>
        
        <div class="document-title">ORDEN DE VENTA N° <?php echo $orden['codigo']; ?></div>
        
        <div class="order-info">
            <div class="order-info-box">
                <div><strong>Cliente:</strong> <?php echo $orden['cliente']; ?></div>
                <div><strong>RUC:</strong> <?php echo $orden['ruc']; ?></div>
                <div><strong>Dirección:</strong> <?php echo $orden['direccion']; ?></div>
                <div><strong>Teléfono:</strong> <?php echo $orden['telefono']; ?></div>
                <div><strong>Email:</strong> <?php echo $orden['email']; ?></div>
            </div>
            
            <div class="order-info-box">
                <div><strong>Fecha de Emisión:</strong> <?php echo formatearFecha($orden['fecha_emision']); ?></div>
                <div><strong>Condiciones de Pago:</strong> <?php echo $orden['condiciones_pago']; ?></div>
                <div><strong>Tiempo de Entrega:</strong> <?php echo $orden['tiempo_entrega']; ?></div>
                <div><strong>Estado:</strong> <span class="estado <?php echo obtenerClaseEstado($orden['estado']); ?>"><?php echo obtenerEstado($orden['estado']); ?></span></div>
                <?php if ($orden['id_proforma']): ?>
                    <div><strong>Proforma Origen:</strong> <?php echo $orden['codigo_proforma']; ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="order-meta">
            <div class="meta-item">
                <span class="meta-label">Vendedor</span>
                <span><?php echo $orden['usuario_nombre'] . ' ' . $orden['usuario_apellido']; ?></span>
            </div>
            
            <div class="meta-item">
                <span class="meta-label">N° de Orden</span>
                <span><?php echo $orden['codigo']; ?></span>
            </div>
            
            <div class="meta-item">
                <span class="meta-label">Fecha</span>
                <span><?php echo formatearFecha($orden['fecha_emision']); ?></span>
            </div>
            
            <div class="meta-item">
                <span class="meta-label">Estado</span>
                <span><?php echo obtenerEstado($orden['estado']); ?></span>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th width="5%">Item</th>
                    <th width="35%">Descripción</th>
                    <th width="20%">Medidas</th>
                    <th width="15%">Material/Detalles</th>
                    <th width="5%">Cant.</th>
                    <th width="10%">Precio Unit.</th>
                    <th width="10%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $item = 1; foreach($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo $item++; ?></td>
                        <td><?php echo $detalle['descripcion']; ?></td>
                        <td>
                            <?php 
                            // Si hay espesor, usarlo en lugar del micraje
                            if (!empty($detalle['espesor'])) {
                                echo $detalle['ancho'] . ' x ' . $detalle['largo'] . ' x ' . $detalle['espesor'];
                            } else {
                                echo $detalle['ancho'] . ' x ' . $detalle['largo'] . ' x ' . $detalle['micraje'] . ' mic';
                            }
                            
                            if ($detalle['fuelle'] > 0) {
                                echo ' (Fuelle: ' . $detalle['fuelle'] . ')';
                            }
                            
                            // Si hay medida referencial, mostrarla
                            if (!empty($detalle['medida_referencial'])) {
                                echo '<br><small>' . $detalle['medida_referencial'] . '</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo $detalle['material']; ?><br>
                            <?php 
                            if ($detalle['colores'] > 0) {
                                echo 'Colores: ' . $detalle['colores'];
                            } else {
                                echo 'Sin color';
                            }
                            
                            if ($detalle['biodegradable']) {
                                echo '<br>Biodegradable';
                            }
                            ?>
                        </td>
                        <td class="text-right"><?php echo number_format($detalle['cantidad']); ?></td>
                        <td class="text-right">S/ <?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                        <td class="text-right">S/ <?php echo number_format($detalle['subtotal'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total-section">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value">S/ <?php echo number_format($orden['subtotal'], 2); ?></div>
            </div>
            <div class="total-row">
                <div class="total-label">IGV (18%):</div>
                <div class="total-value">S/ <?php echo number_format($orden['impuestos'], 2); ?></div>
            </div>
            <div class="total-row grand-total">
                <div class="total-label">TOTAL:</div>
                <div class="total-value">S/ <?php echo number_format($orden['total'], 2); ?></div>
            </div>
        </div>
        
        <?php if (!empty($orden['observaciones'])): ?>
            <div class="notes">
                <strong>Observaciones:</strong><br>
                <?php echo nl2br(htmlspecialchars($orden['observaciones'])); ?>
            </div>
        <?php endif; ?>
        
        <div class="terms">
            <div class="terms-title">Términos y Condiciones:</div>
            <ol>
                <li>Los precios incluyen IGV.</li>
                <li>La mercadería queda en garantía hasta la cancelación total de la factura.</li>
                <li>El plazo de entrega se contabiliza a partir de la aprobación formal de esta orden.</li>
                <li>Cualquier cambio en las especificaciones debe ser aprobado por escrito.</li>
                <li>El cliente se compromete a verificar las medidas y especificaciones al recibir el producto.</li>
            </ol>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Firma del Vendedor</div>
                <div><?php echo $orden['usuario_nombre'] . ' ' . $orden['usuario_apellido']; ?></div>
            </div>
            
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Firma del Cliente</div>
                <div><?php echo $orden['cliente']; ?></div>
            </div>
        </div>
        
        <div class="footer">
            <div>Documento generado el <?php echo date('d/m/Y H:i:s'); ?></div>
            <div>Este documento no tiene validez fiscal y es únicamente para uso interno.</div>
            <div>&copy; <?php echo date('Y'); ?> <?php echo $empresa['nombre']; ?> - Todos los derechos reservados</div>
        </div>
        
        <div class="no-print">
            <button onclick="window.location.href='ver_orden.php?id=<?php echo $id_orden; ?>'" class="btn-back">Volver</button>
            <button onclick="window.print()" class="btn-print">Imprimir Documento</button>
        </div>
        
        <div class="print-only">
            <div style="margin-top: 20px; font-size: 8px; text-align: center; color: #999;">
                Documento impreso desde el Sistema ERP KEYDAN - <?php echo date('d/m/Y H:i:s'); ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto imprimir cuando se carga la página (opcional)
        window.onload = function() {
            // Descomenta la siguiente línea si deseas que se imprima automáticamente
            // window.print();
        };
    </script>
</body>
</html>