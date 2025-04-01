<?php
// imprimir_proforma.php
require_once 'config/db.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    exit("ID de proforma no proporcionado");
}

$id_proforma = $_GET['id'];

try {
    // Obtener datos de la proforma
    $stmt = $conn->prepare("SELECT p.*, cl.razon_social as cliente, cl.ruc, cl.direccion, cl.telefono, cl.email,
                           u.nombre as usuario_nombre, u.apellido as usuario_apellido 
                           FROM proformas p 
                           JOIN clientes cl ON p.id_cliente = cl.id_cliente 
                           JOIN usuarios u ON p.id_usuario = u.id_usuario 
                           WHERE p.id_proforma = :id");
    $stmt->bindParam(':id', $id_proforma);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        exit("Proforma no encontrada");
    }
    
    $proforma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles de la proforma
    $stmt = $conn->prepare("SELECT pd.*, m.nombre as material 
                           FROM proforma_detalles pd 
                           JOIN materiales m ON pd.id_material = m.id_material 
                           WHERE pd.id_proforma = :id");
    $stmt->bindParam(':id', $id_proforma);
    $stmt->execute();
    
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    exit("Error al obtener la proforma: " . $e->getMessage());
}

// Configuración de la empresa
$empresa = [
    'nombre' => 'KEYDAN S.A.C.',
    'ruc' => '20611150564',
    'direccion' => 'CAL URAN MARCA NRO. 203 COO. LOS CHANCAS DE ANDAHUAYLAS LIMA - LIMA - SANTA ANITA',
    'telefono' => '985 640 149 - 901 010 575 - 928 079 130',
    'email' => 'keydansac@gmail.com',
    'facebook' => 'facebook/keydan11',
    'banco' => 'BANCO DE CREDITO DEL PERU (BCP)',
    'cuenta' => '475-1066-277-054',
    'cci' => '002-475-00-1066-277-05-227'
];

// Calcular Monto Base (SUB TOTAL sin IGV) y IGV
$monto_base = $proforma['subtotal'] / 1.18;
$igv = $monto_base * 0.18;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma <?php echo $proforma['codigo']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
            color: #000;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 3px solid #ff0000;
        }
        th, td {
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #f9f9f9;
        }
        .header-table {
            border: 3px solid #ff0000;
            margin-bottom: 0px;
        }
        .header-table td {
            vertical-align: top;
            border: none; /* Eliminar líneas de separación verticales */
        }
        .logo {
            width: 90px;
            height: auto;
            margin-left: 20px;
        }
        .company-name {
            color: #0099cc;
            font-size: 25px;
            font-weight: bold;
            display: inline-block;
            
        }
        .ruc {
            font-size: 25px;
            color: #000;
            display: block;
            margin-left: 20px;
        }
        .contact {
            font-size: 10px;
            text-align: left;
            color: #000;
        }
        .address {
            font-size: 12px;
            text-align: left;
            color: #000;
        }
        .proforma-header {
            border: 3px solid #ff0000;
            color:  #ff0000;
            text-align: center;
            padding: 5px 0;
            font-weight: bold;
            font-size: 35px;
            margin: 0;
        }
        .client-table {
            border: 3px solid #ff0000;
            margin-top: 0;
            margin-bottom: 0;
        }
        .client-label {
            color: #0099cc;
            font-weight: bold;
            width: 100px;
            
        }
        .proforma-label {
            color: #0099cc;
            text-align: right;
            font-weight: bold;
            text-align: left;
        }
        .proforma-data {
            text-align: right;
        }
        .products-header {
            background-color: white;
            border : 3px solid #ff0000;
            color:  #ff0000;
            text-align: center;
            font-weight: bold;
            padding: 5px 0;
            margin: 0;
            font-size: 15px;
        }
        .products-table th {
            text-align: center;
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .products-table td {
            vertical-align: top;
        }
        .text-center {
            text-align: center;
        }
        .note-header {
            background-color: white;
            border: 3px solid #ff0000;
            color: #ff0000;
            text-align: left;
            font-weight: bold;
            padding: 5px;
            margin: 0;
        }
        .note-content {
            padding: 10px;
            border: 3px solid #ff0000;
            border-top: none;
        }
        .footer {
            margin-top: 20px;
            font-size: 10px;
            border: 3px solid #ff0000;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .no-print {
            display: block;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                width: 100%;
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .container {
                width: 100%;
            }
            .proforma-header, .products-header, .note-header {
                border: 3px solid #ff0000;
            color:  #ff0000;
            }
            table, th, td {
                border: 3px solid #ff0000 !important;
            }
            .company-name {
                color: #0099cc !important;
            }
            .address, .contact {
                color: #000 !important;
            }
            .header-table td {
                border: none !important; /* Mantener sin líneas de separación al imprimir */
            }
        }
    </style>
</head>
<body>
    <button onclick="window.print();" class="print-button no-print">Imprimir</button>
    
    <div class="container">
        <!-- Encabezado de la empresa -->
        <table class="header-table">
            <tr>
                <td width="20%" style="vertical-align: middle;">
                    <img src="assets/img/logo.png" alt="Logo KEYDAN" class="logo">
                </td>
                <td width="80%" style="vertical-align: middle;">
                    <div class="company-name"><?php echo $empresa['nombre']; ?></div>
                    <div class="address">
                        RUC: <?php echo $empresa['ruc']; ?><br>
                        Domicilio Fiscal:<br>
                        <?php echo $empresa['direccion']; ?><br>
                        <?php echo $empresa['telefono']; ?><br>
                    <?php echo $empresa['email']; ?><br>
                    <?php echo $empresa['facebook']; ?>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Título de la Proforma -->
        <div class="proforma-header">PROFORMA</div>
        
        <!-- Información del cliente -->
        <table class="client-table">
            <tr>
                <td width="50%">
                    <table border="0" cellpadding="2" cellspacing="0">
                        <tr>
                            <td class="client-label">RUC:</td>
                            <td><?php echo $proforma['ruc']; ?></td>
                        </tr>
                        <tr>
                            <td class="client-label">RAZÓN SOCIAL:</td>
                            <td><?php echo $proforma['cliente']; ?></td>
                        </tr>
                        <tr>
                            <td class="client-label">DIRECCIÓN:</td>
                            <td><?php echo $proforma['direccion']; ?></td>
                        </tr>
                        <tr>
                            <td class="client-label">CELULAR:</td>
                            <td><?php echo $proforma['telefono']; ?></td>
                        </tr>
                    </table>
                </td>
                <td width="50%">
                    <table border="0" cellpadding="2" cellspacing="0" width="100%">
                        <tr>
                            <td class="proforma-label">PROFORMA</td>
                            <td class="proforma-data"><?php echo $proforma['codigo']; ?></td>
                        </tr>
                        <tr>
                            <td class="proforma-label">Fecha</td>
                            <td class="proforma-data"><?php echo date('d/m/Y', strtotime($proforma['fecha_emision'])); ?></td>
                        </tr>
                        <tr>
                            <td class="proforma-label">Vendedor</td>
                            <td class="proforma-data"><?php echo $proforma['usuario_nombre'] . ' ' . $proforma['usuario_apellido']; ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <!-- Productos -->
        <div class="products-header">PRODUCTOS</div>
        
        <table class="table table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>CANT.</th>
                    <th>UNIDAD</th>
                    <th>PRODUCTO</th>
                    <th>PRECIO UNITARIO</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo number_format($detalle['cantidad'] / 100); ?></td>
                        <td>paquete</td>
                        <td>
                            <?php 
                            $ancho_pulg = $detalle['ancho'];
                            $largo_pulg = $detalle['largo'];
                            ?>
                            <strong><?php echo "Bolsas de polietileno: " . $detalle['medida_referencial']; ?></strong><br>
                            <strong>Dimensiones:</strong> <?php echo $ancho_pulg; ?>" x <?php echo $largo_pulg; ?>" (<?php echo number_format($ancho_pulg * 2.54, 1); ?> x <?php echo number_format($largo_pulg * 2.54, 1); ?> cm)
                            
                            <?php if (!empty($detalle['medida_referencial'])): ?>
                            <br><strong>Material:</strong> <?php echo $detalle['material']; ?>
                            <?php endif; ?>
                            
                            <?php if ($detalle['fuelle'] > 0): ?>
                            <br><strong>Fuelle:</strong> <?php echo $detalle['fuelle']; ?>" (<?php echo number_format($detalle['fuelle'] * 2.54, 2); ?> cm)
                            <?php endif; ?>
                            
                            <br><strong>Espesor:</strong> <?php echo $detalle['espesor']; ?>
                            <br><strong>Color:</strong> <?php echo (!empty($detalle['color_texto'])) ? $detalle['color_texto'] : ($detalle['colores'] == 1 ? 'Colores' : 'Negro'); ?>
                            <br><strong>Presentación:</strong> 100 unidades
                            
                            <?php if ($detalle['biodegradable']): ?>
                            <br><strong>Biodegradable:</strong> Sí
                            <?php endif; ?>
                        </td>
                        <td>S/ <?php echo number_format($detalle['precio_unitario'], 1); ?></td>
                        <td>S/ <?php echo number_format($detalle['subtotal'], 1); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Nota y Total -->
        <div class="note-header">NOTA</div>
        
        <div class="note-content">
            <div style="float: left; width: 60%;">
                
                <p>* Uso de micrometro para medir el espesor de la bolsa.</p>
                <p>* Para el procesamiento de la mercadería, se debe realizar un monto del 50% del total y el otro 50% cuando se realiza la entrega de la mercadería.</p>
                <p>* El plazo de entrega comienza a correr luego del adelanto acordado.</p>
                <p>* El costo de reparto para Lima Metropolitana o hasta la agencia para provincias es gratuito, siempre y cuando el monto sea superior a S/ 1,500.00 soles.</p>
            </div>
            <div style="float: right; width: 30%; text-align: right;">
                <p><strong>Monto Base:</strong> S/ <?php echo number_format($monto_base, 2); ?></p>
                <p><strong>IGV (18%):</strong> S/ <?php echo number_format($igv, 2); ?></p>
                <p><strong>TOTAL S/:</strong> S/ <?php echo number_format($proforma['subtotal'], 2); ?></p>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <!-- Pie de página -->
        <div class="footer">
            <table border="0" cellpadding="5" cellspacing="0" width="100%">
                <tr>
                   <tr>
                        <!-- Encabezado: Números de cuenta -->
                        <td colspan="3" style="text-align: center; background-color: #f9f9f9; font-size: 15px;">
                            <strong>NÚMEROS DE CUENTA</strong><br
                            <strong>KEYDAN S.A.C.</strong>
                        </td>
                    </tr>
                    <tr>
                        <!-- Números de cuenta de KEYDAN SAC -->
                        <td colspan="2" style="text-align: center; font-size: 13px;" width="50%">
                            <strong ><?php echo $empresa['banco']; ?></strong><br>
                            N° cuenta: <?php echo $empresa['cuenta']; ?><br>
                            CCI: <?php echo $empresa['cci']; ?>
                        </td>
                        
                        <!-- Yape -->
                        <td style="text-align: center; font-size: 13px;" width="50%">
                            <strong>YAPE</strong><br>
                            985 640 149
                        </td>
                    </tr>
                    <td width="33%" style="text-align: center; vertical-align: top;">
                        <strong>LUGAR DE ENTREGA</strong><br>
                        TIENDA
                    </td>
                    <td width="33%" style="text-align: center; vertical-align: top;">
                        <strong>LOCAL KEYDAN</strong><br>
                        CLIENTE
                    </td>
                </tr>
                <tr>
                    <td style="text-align: center;">
                        <strong>ETIQUETA</strong><br>
                        PERSONALIZADO
                    </td>
                    <td style="text-align: center;">
                        <strong>PLAZO DE ENTREGA</strong><br>
                        5 DÍAS
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="3" style="font-style: italic; text-align: center; color: blue; padding: 10px;">
                        Estimado cliente, le agradecemos por confiar en nosotros.<br>
                        <strong>¡GRACIAS! Su total confianza en nuestro trabajo nos fortalece</strong>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>