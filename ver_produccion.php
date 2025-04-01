<li class="nav-item">
        <a class="nav-link active" id="productos-tab" data-toggle="tab" href="#productos" role="tab" aria-controls="productos" aria-selected="true">
            <i class="fas fa-box-open"></i> Productos (<?php echo count($detalles); ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="etapas-tab" data-toggle="tab" href="#etapas" role="tab" aria-controls="etapas" aria-selected="false">
            <i class="fas fa-tasks"></i> Etapas de Producción
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="rollos-tab" data-toggle="tab" href="#rollos" role="tab" aria-controls="rollos" aria-selected="false">
            <i class="fas fa-dolly"></i> Materia Prima (<?php echo count($rollos); ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="desperdicios-tab" data-toggle="tab" href="#desperdicios" role="tab" aria-controls="desperdicios" aria-selected="false">
            <i class="fas fa-trash-alt"></i> Desperdicios (<?php echo count($desperdicios); ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="historial-tab" data-toggle="tab" href="#historial" role="tab" aria-controls="historial" aria-selected="false">
            <i class="fas fa-history"></i> Historial (<?php echo count($historial); ?>)
        </a>
    </li>
</ul>

<div class="tab-content p-0 mt-3" id="produccionTabsContent">
    <!-- Tab Productos -->
    <div class="tab-pane fade show active" id="productos" role="tabpanel" aria-labelledby="productos-tab">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Detalle de Productos en Producción</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 25%;">Producto</th>
                                <th style="width: 20%;">Especificaciones</th>
                                <th style="width: 10%;">Cantidad</th>
                                <th style="width: 10%;">Progreso</th>
                                <th style="width: 15%;">Etapas</th>
                                <th style="width: 15%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($detalles) > 0): ?>
                                <?php foreach($detalles as $index => $detalle): ?>
                                    <?php 
                                    // Calcular porcentaje de avance para este detalle
                                    $porcentaje_detalle = ($detalle['cantidad_programada'] > 0) ? 
                                        round(($detalle['cantidad_producida'] / $detalle['cantidad_programada']) * 100) : 0;
                                        
                                    // Calcular porcentaje de etapas completadas
                                    $porcentaje_etapas_detalle = ($detalle['total_etapas'] > 0) ?
                                        round(($detalle['etapas_completadas'] / $detalle['total_etapas']) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo $detalle['descripcion']; ?></strong><br>
                                            <small class="text-muted">Material: <?php echo $detalle['material']; ?></small>
                                        </td>
                                        <td>
                                            <span class="d-block">
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
                                                ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php
                                                if ($detalle['colores'] > 0) {
                                                    echo 'Colores: ' . $detalle['colores'];
                                                } else {
                                                    echo 'Sin color';
                                                }
                                                
                                                if ($detalle['biodegradable']) {
                                                    echo ' - Biodegradable';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <strong><?php echo number_format($detalle['cantidad_producida']); ?></strong> de 
                                            <?php echo number_format($detalle['cantidad_programada']); ?>
                                            <div class="small text-muted">
                                                <?php if ($detalle['peso_real']): ?>
                                                    Peso: <?php echo number_format($detalle['peso_real'], 2); ?> kg
                                                <?php else: ?>
                                                    Peso est.: <?php echo number_format($detalle['peso_estimado'], 2); ?> kg
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress mb-2" style="height: 20px;">
                                                <div class="progress-bar 
                                                    <?php 
                                                    if ($porcentaje_detalle >= 100) {
                                                        echo 'bg-success';
                                                    } elseif ($porcentaje_detalle >= 75) {
                                                        echo 'bg-info';
                                                    } elseif ($porcentaje_detalle >= 50) {
                                                        echo 'bg-primary';
                                                    } elseif ($porcentaje_detalle >= 25) {
                                                        echo 'bg-warning';
                                                    } else {
                                                        echo 'bg-danger';
                                                    }
                                                    ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo $porcentaje_detalle; ?>%" 
                                                    aria-valuenow="<?php echo $porcentaje_detalle; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                    <?php echo $porcentaje_detalle; ?>%
                                                </div>
                                            </div>
                                            <div class="small text-center">
                                                Estado: 
                                                <span class="badge 
                                                    <?php 
                                                    switch($detalle['estado']) {
                                                        case 'pendiente': echo 'badge-secondary'; break;
                                                        case 'en_proceso': echo 'badge-info'; break;
                                                        case 'completado': echo 'badge-success'; break;
                                                        default: echo 'badge-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($detalle['estado']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress mb-2" style="height: 20px;">
                                                <div class="progress-bar bg-info" role="progressbar" 
                                                    style="width: <?php echo $porcentaje_etapas_detalle; ?>%" 
                                                    aria-valuenow="<?php echo $porcentaje_etapas_detalle; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                    <?php echo $porcentaje_etapas_detalle; ?>%
                                                </div>
                                            </div>
                                            <div class="small text-center">
                                                <?php echo $detalle['etapas_completadas']; ?> de <?php echo $detalle['total_etapas']; ?> etapas
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($produccion['estado'] != 'completada' && $produccion['estado'] != 'cancelada' && ($produccion['estado'] == 'en_proceso' || $produccion['estado'] == 'pausada')): ?>
                                                <a href="registrar_avance.php?id=<?php echo $id_orden_produccion; ?>&detalle=<?php echo $detalle['id_produccion_detalle']; ?>" class="btn btn-sm btn-info mb-1">
                                                    <i class="fas fa-tasks"></i> Registrar Avance
                                                </a>
                                                <a href="gestionar_etapas.php?id=<?php echo $id_orden_produccion; ?>&detalle=<?php echo $detalle['id_produccion_detalle']; ?>" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-cogs"></i> Gestionar Etapas
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay productos en esta orden de producción</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tab Etapas de Producción -->
    <div class="tab-pane fade" id="etapas" role="tabpanel" aria-labelledby="etapas-tab">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Etapas de Producción</h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $conn->prepare("SELECT pe.*, pd.id_produccion_detalle, od.descripcion,
                                      CONCAT(u.nombre, ' ', u.apellido) as operario
                                      FROM produccion_etapas pe
                                      JOIN produccion_detalles pd ON pe.id_produccion_detalle = pd.id_produccion_detalle
                                      JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                                      LEFT JOIN usuarios u ON pe.id_operario = u.id_usuario
                                      WHERE pd.id_orden_produccion = :id
                                      ORDER BY pd.id_produccion_detalle, FIELD(pe.tipo_etapa, 'corte', 'sellado', 'control_calidad', 'empaque')");
                $stmt->bindParam(':id', $id_orden_produccion);
                $stmt->execute();
                $todas_etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $etapas_por_producto = [];
                foreach ($todas_etapas as $etapa) {
                    $id_detalle = $etapa['id_produccion_detalle'];
                    if (!isset($etapas_por_producto[$id_detalle])) {
                        $etapas_por_producto[$id_detalle] = [
                            'descripcion' => $etapa['descripcion'],
                            'etapas' => []
                        ];
                    }
                    $etapas_por_producto[$id_detalle]['etapas'][] = $etapa;
                }
                ?>
                
                <?php if (count($etapas_por_producto) > 0): ?>
                    <div class="accordion" id="accordionEtapas">
                        <?php foreach ($etapas_por_producto as $id_detalle => $producto): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light" id="heading<?php echo $id_detalle; ?>">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapse<?php echo $id_detalle; ?>" aria-expanded="true" aria-controls="collapse<?php echo $id_detalle; ?>">
                                            <i class="fas fa-box-open mr-2"></i> <?php echo $producto['descripcion']; ?>
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapse<?php echo $id_detalle; ?>" class="collapse" aria-labelledby="heading<?php echo $id_detalle; ?>" data-parent="#accordionEtapas">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Etapa</th>
                                                        <th>Estado</th>
                                                        <th>Operario</th>
                                                        <th>Fecha Inicio</th>
                                                        <th>Fecha Fin</th>
                                                        <th>Cantidad Procesada</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($producto['etapas'] as $etapa): ?>
                                                        <tr>
                                                            <td>
                                                                <?php 
                                                                switch($etapa['tipo_etapa']) {
                                                                    case 'corte': echo '<i class="fas fa-cut mr-1"></i> Corte'; break;
                                                                    case 'sellado': echo '<i class="fas fa-fire mr-1"></i> Sellado'; break;
                                                                    case 'control_calidad': echo '<i class="fas fa-clipboard-check mr-1"></i> Control de Calidad'; break;
                                                                    case 'empaque': echo '<i class="fas fa-box mr-1"></i> Empaque'; break;
                                                                    default: echo ucfirst($etapa['tipo_etapa']);
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge 
                                                                    <?php 
                                                                    switch($etapa['estado']) {
                                                                        case 'pendiente': echo 'badge-secondary'; break;
                                                                        case 'en_proceso': echo 'badge-info'; break;
                                                                        case 'completado': echo 'badge-success'; break;
                                                                        default: echo 'badge-secondary';
                                                                    }
                                                                    ?>">
                                                                    <?php echo ucfirst($etapa['estado']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php echo $etapa['operario'] ? $etapa['operario'] : '<span class="text-muted">No asignado</span>'; ?>
                                                            </td>
                                                            <td>
                                                                <?php echo $etapa['fecha_inicio'] ? date('d/m/Y H:i', strtotime($etapa['fecha_inicio'])) : '<span class="text-muted">Pendiente</span>'; ?>
                                                            </td>
                                                            <td>
                                                                <?php echo $etapa['fecha_fin'] ? date('d/m/Y H:i', strtotime($etapa['fecha_fin'])) : '<span class="text-muted">Pendiente</span>'; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php echo $etapa['cantidad_procesada'] ? number_format($etapa['cantidad_procesada']) : '<span class="text-muted">0</span>'; ?>
                                                                <?php if ($etapa['cantidad_defectuosa'] > 0): ?>
                                                                    <br><small class="text-danger">Defectuosos: <?php echo number_format($etapa['cantidad_defectuosa']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($produccion['estado'] != 'completada' && $produccion['estado'] != 'cancelada' && ($produccion['estado'] == 'en_proceso' || $produccion['estado'] == 'pausada')): ?>
                                                                    <?php if ($etapa['estado'] == 'pendiente'): ?>
                                                                        <a href="iniciar_etapa.php?id=<?php echo $etapa['id_etapa']; ?>" class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-play"></i> Iniciar
                                                                        </a>
                                                                    <?php elseif ($etapa['estado'] == 'en_proceso'): ?>
                                                                        <a href="completar_etapa.php?id=<?php echo $etapa['id_etapa']; ?>" class="btn btn-sm btn-success">
                                                                            <i class="fas fa-check"></i> Completar
                                                                        </a>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay etapas de producción registradas para esta orden.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tab Materia Prima (Rollos) -->
    <div class="tab-pane fade" id="rollos" role="tabpanel" aria-labelledby="rollos-tab">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Materia Prima Asignada</h5>
            </div>
            <div class="card-body">
                <?php if (count($rollos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Código Rollo</th>
                                    <th>Material</th>
                                    <th>Etapa</th>
                                    <th>Peso Asignado</th>
                                    <th>Peso Consumido</th>
                                    <th>Fecha Asignación</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rollos as $rollo): ?>
                                    <?php 
                                    $porcentaje_consumo = ($rollo['peso_asignado'] > 0) ? 
                                        round(($rollo['peso_consumido'] / $rollo['peso_asignado']) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $rollo['codigo_rollo']; ?></td>
                                        <td>
                                            <?php echo $rollo['material']; ?><br>
                                            <small class="text-muted">Color: <?php echo $rollo['color']; ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            switch($rollo['tipo_etapa']) {
                                                case 'corte': echo '<i class="fas fa-cut mr-1"></i> Corte'; break;
                                                case 'sellado': echo '<i class="fas fa-fire mr-1"></i> Sellado'; break;
                                                case 'control_calidad': echo '<i class="fas fa-clipboard-check mr-1"></i> Control de Calidad'; break;
                                                case 'empaque': echo '<i class="fas fa-box mr-1"></i> Empaque'; break;
                                                default: echo ucfirst($rollo['tipo_etapa']);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo number_format($rollo['peso_asignado'], 2); ?> kg</td>
                                        <td>
                                            <?php echo number_format($rollo['peso_consumido'], 2); ?> kg
                                            <div class="progress mt-1" style="height: 10px;">
                                                <div class="progress-bar 
                                                    <?php 
                                                    if ($porcentaje_consumo >= 100) {
                                                        echo 'bg-success';
                                                    } elseif ($porcentaje_consumo >= 75) {
                                                        echo 'bg-info';
                                                    } elseif ($porcentaje_consumo >= 50) {
                                                        echo 'bg-primary';
                                                    } elseif ($porcentaje_consumo >= 25) {
                                                        echo 'bg-warning';
                                                    } else {
                                                        echo 'bg-danger';
                                                    }
                                                    ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo $porcentaje_consumo; ?>%" 
                                                    aria-valuenow="<?php echo $porcentaje_consumo; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($rollo['fecha_asignacion'])); ?></td>
                                        <td><?php echo $rollo['usuario_nombre'] . ' ' . $rollo['usuario_apellido']; ?></td>
                                        <td>
                                            <?php if ($rollo['fecha_finalizacion']): ?>
                                                <span class="badge badge-success">Finalizado</span>
                                            <?php elseif ($porcentaje_consumo >= 100): ?>
                                                <span class="badge badge-info">Consumido</span>
                                            <?php else: ?>
                                                <span class="badge badge-primary">En uso</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay rollos de materia prima asignados a esta producción.
                    </div>
                    <?php if ($produccion['estado'] != 'completada' && $produccion['estado'] != 'cancelada'): ?>
                        <div class="text-center">
                            <a href="asignar_rollos.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-primary">
                                <i class="fas fa-dolly"></i> Asignar Rollos
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tab Desperdicios -->
    <div class="tab-pane fade" id="desperdicios" role="tabpanel" aria-labelledby="desperdicios-tab">
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Desperdicios Registrados</h5>
                <?php if ($produccion['estado'] != 'completada' && $produccion['estado'] != 'cancelada'): ?>
                    <a href="registrar_desperdicio.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> Registrar Desperdicio
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($desperdicios) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Etapa</th>
                                    <th>Peso</th>
                                    <th>Motivo</th>
                                    <th>Registrado por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($desperdicios as $desperdicio): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($desperdicio['fecha'])); ?></td>
                                        <td>
                                            <?php 
                                            switch($desperdicio['tipo']) {
                                                case 'cono': echo '<span class="badge badge-secondary">Cono</span>'; break;
                                                case 'scrap': echo '<span class="badge badge-warning">Scrap</span>'; break;
                                                case 'otro': echo '<span class="badge badge-info">Otro</span>'; break;
                                                default: echo ucfirst($desperdicio['tipo']);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            switch($desperdicio['tipo_etapa']) {
                                                case 'corte': echo '<i class="fas fa-cut mr-1"></i> Corte'; break;
                                                case 'sellado': echo '<i class="fas fa-fire mr-1"></i> Sellado'; break;
                                                case 'control_calidad': echo '<i class="fas fa-clipboard-check mr-1"></i> Control de Calidad'; break;
                                                case 'empaque': echo '<i class="fas fa-box mr-1"></i> Empaque'; break;
                                                default: echo ucfirst($desperdicio['tipo_etapa']);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo number_format($desperdicio['peso'], 2); ?> kg</td>
                                        <td><?php echo $desperdicio['motivo']; ?></td>
                                        <td><?php echo $desperdicio['usuario_nombre'] . ' ' . $desperdicio['usuario_apellido']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-light">
                                    <th colspan="3" class="text-right">Total:</th>
                                    <th>
                                        <?php
                                        $total_desperdicios = 0;
                                        foreach($desperdicios as $desperdicio) {
                                            $total_desperdicios += $desperdicio['peso'];
                                        }
                                        echo number_format($total_desperdicios, 2) . ' kg';
                                        ?>
                                    </th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay desperdicios registrados para esta producción.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tab Historial -->
    <div class="tab-pane fade" id="historial" role="tabpanel" aria-labelledby="historial-tab">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Historial de la Producción</h5>
            </div>
            <div class="card-body">
                <?php if (count($historial) > 0): ?>
                    <div class="timeline">
                        <?php foreach($historial as $evento): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker 
                                    <?php 
                                    switch($evento['tipo_evento']) {
                                        case 'inicio_produccion': echo 'bg-primary'; break;
                                        case 'fin_etapa': echo 'bg-success'; break;
                                        case 'pausa': echo 'bg-warning'; break;
                                        case 'reanudacion': echo 'bg-info'; break;
                                        case 'cambio_estado': echo 'bg-danger'; break;
                                        default: echo 'bg-secondary';
                                    }
                                    ?>">
                                    <?php 
                                    switch($evento['tipo_evento']) {
                                        case 'inicio_produccion': echo '<i class="fas fa-play"></i>'; break;
                                        case 'fin_etapa': echo '<i class="fas fa-check"></i>'; break;
                                        case 'pausa': echo '<i class="fas fa-pause"></i>'; break;
                                        case 'reanudacion': echo '<i class="fas fa-redo"></i>'; break;
                                        case 'cambio_estado': echo '<i class="fas fa-exchange-alt"></i>'; break;
                                        default: echo '<i class="fas fa-circle"></i>';
                                    }
                                    ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-heading">
                                        <h6 class="mb-0">
                                            <?php 
                                            switch($evento['tipo_evento']) {
                                                case 'inicio_produccion': echo 'Inicio de producción'; break;
                                                case 'fin_etapa': echo 'Finalización de etapa'; break;
                                                case 'pausa': echo 'Producción pausada'; break;
                                                case 'reanudacion': echo 'Producción reanudada'; break;
                                                case 'cambio_estado': echo 'Cambio de estado'; break;
                                                default: echo ucfirst(str_replace('_', ' ', $evento['tipo_evento']));
                                            }
                                            ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?> -
                                            Por: <?php echo $evento['usuario_nombre'] . ' ' . $evento['usuario_apellido']; ?>
                                        </small>
                                    </div>
                                    <div class="timeline-body">
                                        <p><?php echo nl2br(htmlspecialchars($evento['descripcion'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay eventos en el historial para esta producción.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sección de observaciones -->
<?php if (!empty($produccion['observaciones'])): ?>
<div class="card mt-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Observaciones</h5>
    </div>
    <div class="card-body">
        <p><?php echo nl2br(htmlspecialchars($produccion['observaciones'])); ?></p>
    </div>
</div>
<?php endif; ?>

<style>
/* Estilos para el timeline */
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline-item {
    position: relative;
    display: flex;
    margin-bottom: 20px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #007bff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    margin-right: 15px;
    flex-shrink: 0;
}

.timeline-content {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    flex-grow: 1;
}

.timeline-heading {
    margin-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.timeline-body p:last-child {
    margin-bottom: 0;
}

.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.badge {
    font-size: 85%;
    padding: 0.4em 0.6em;
}

.card .position-absolute {
    z-index: 1;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Abrir pestaña activa guardada en localStorage (si existe)
    const activeTab = localStorage.getItem('produccionActiveTab');
    if (activeTab) {
        try {
            const tab = document.querySelector(activeTab);
            if (tab) {
                const tabTrigger = new bootstrap.Tab(tab);
                tabTrigger.show();
            }
        } catch (e) {
            console.error('Error al activar pestaña:', e);
        }
    }
    
    // Guardar pestaña activa en localStorage al cambiar
    const tabLinks = document.querySelectorAll('a[data-toggle="tab"]');
    tabLinks.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            localStorage.setItem('produccionActiveTab', e.target.getAttribute('href'));
        });
    });
    
    // Abrir automáticamente el primer acordeón de etapas
    const firstAccordion = document.querySelector('#accordionEtapas .collapse:first-child');
    if (firstAccordion) {
        firstAccordion.classList.add('show');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
<?php
require_once 'includes/header.php';
require_once 'config/db.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: produccion.php?error=ID de orden de producción no proporcionado");
    exit;
}

$id_orden_produccion = $_GET['id'];

try {
    // Obtener datos de la orden de producción
    $stmt = $conn->prepare("SELECT op.*, o.codigo as codigo_orden, o.id_orden, 
                          cl.razon_social as cliente, cl.ruc, cl.direccion, cl.telefono, cl.email,
                          u1.nombre as responsable_nombre, u1.apellido as responsable_apellido,
                          u2.nombre as usuario_nombre, u2.apellido as usuario_apellido
                          FROM ordenes_produccion op
                          JOIN ordenes_venta o ON op.id_orden = o.id_orden
                          JOIN clientes cl ON o.id_cliente = cl.id_cliente
                          JOIN usuarios u1 ON op.id_responsable = u1.id_usuario
                          JOIN usuarios u2 ON op.id_usuario_creacion = u2.id_usuario
                          WHERE op.id_orden_produccion = :id");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header("Location: produccion.php?error=Orden de producción no encontrada");
        exit;
    }
    
    $produccion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles de producción
    $stmt = $conn->prepare("SELECT pd.*, od.descripcion, od.ancho, od.largo, od.micraje, od.espesor, 
                          od.fuelle, od.colores, od.biodegradable, m.nombre as material,
                          (SELECT COUNT(*) FROM produccion_etapas pe WHERE pe.id_produccion_detalle = pd.id_produccion_detalle AND pe.estado = 'completado') as etapas_completadas,
                          (SELECT COUNT(*) FROM produccion_etapas pe WHERE pe.id_produccion_detalle = pd.id_produccion_detalle) as total_etapas
                          FROM produccion_detalles pd
                          JOIN orden_detalles od ON pd.id_orden_detalle = od.id_detalle
                          JOIN materiales m ON od.id_material = m.id_material
                          WHERE pd.id_orden_produccion = :id");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener historial de la producción
    $stmt = $conn->prepare("SELECT hp.*, 
                          u.nombre as usuario_nombre, u.apellido as usuario_apellido
                          FROM historial_produccion hp
                          JOIN usuarios u ON hp.id_usuario = u.id_usuario
                          WHERE hp.id_orden_produccion = :id
                          ORDER BY hp.fecha DESC");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener rollos asignados a esta producción
    $stmt = $conn->prepare("SELECT ar.*, r.codigo as codigo_rollo, r.color, r.peso_inicial, m.nombre as material,
                          pe.tipo_etapa, u.nombre as usuario_nombre, u.apellido as usuario_apellido
                          FROM asignacion_rollos ar
                          JOIN rollos_materia_prima r ON ar.id_rollo = r.id_rollo
                          JOIN materiales m ON r.id_material = m.id_material
                          JOIN produccion_etapas pe ON ar.id_etapa = pe.id_etapa
                          JOIN usuarios u ON ar.id_usuario = u.id_usuario
                          WHERE pe.id_etapa IN (
                              SELECT pe2.id_etapa FROM produccion_etapas pe2
                              JOIN produccion_detalles pd ON pe2.id_produccion_detalle = pd.id_produccion_detalle
                              WHERE pd.id_orden_produccion = :id
                          )
                          ORDER BY ar.fecha_asignacion DESC");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $rollos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener desperdicios registrados
    $stmt = $conn->prepare("SELECT dp.*, 
                          pe.tipo_etapa,
                          u.nombre as usuario_nombre, u.apellido as usuario_apellido
                          FROM desperdicios_produccion dp
                          JOIN produccion_etapas pe ON dp.id_etapa = pe.id_etapa
                          JOIN usuarios u ON dp.id_usuario = u.id_usuario
                          WHERE pe.id_etapa IN (
                              SELECT pe2.id_etapa FROM produccion_etapas pe2
                              JOIN produccion_detalles pd ON pe2.id_produccion_detalle = pd.id_produccion_detalle
                              WHERE pd.id_orden_produccion = :id
                          )
                          ORDER BY dp.fecha DESC");
    $stmt->bindParam(':id', $id_orden_produccion);
    $stmt->execute();
    $desperdicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales
    $total_programado = 0;
    $total_producido = 0;
    $total_peso_estimado = 0;
    $total_peso_real = 0;
    
    foreach ($detalles as $detalle) {
        $total_programado += $detalle['cantidad_programada'];
        $total_producido += $detalle['cantidad_producida'];
        $total_peso_estimado += $detalle['peso_estimado'];
        $total_peso_real += $detalle['peso_real'] ? $detalle['peso_real'] : 0;
    }
    
    $porcentaje_avance = ($total_programado > 0) ? round(($total_producido / $total_programado) * 100) : 0;
    
    // Calcular etapas completadas
    $etapas_completadas = 0;
    $total_etapas = 0;
    
    foreach ($detalles as $detalle) {
        $etapas_completadas += $detalle['etapas_completadas'];
        $total_etapas += $detalle['total_etapas'];
    }
    
    $porcentaje_etapas = ($total_etapas > 0) ? round(($etapas_completadas / $total_etapas) * 100) : 0;
    
} catch(PDOException $e) {
    header("Location: produccion.php?error=" . urlencode("Error al obtener la producción: " . $e->getMessage()));
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="fas fa-industry"></i> Detalle de Orden de Producción</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="produccion.php">Producción</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $produccion['codigo']; ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-md-4 text-right">
        <a href="imprimir_produccion.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-info" target="_blank">
            <i class="fas fa-print"></i> Imprimir
        </a>
        <a href="produccion.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> 
        <?php 
        if (isset($_GET['message'])) {
            echo $_GET['message'];
        } else {
            echo "Operación completada correctamente.";
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_GET['error']; ?>
    </div>
<?php endif; ?>

<!-- Resumen de la producción -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="card-title mb-3">
                            <?php echo $produccion['codigo']; ?>
                            <span class="badge 
                                <?php 
                                switch($produccion['estado']) {
                                    case 'programada': echo 'badge-secondary'; break;
                                    case 'en_proceso': echo 'badge-info'; break;
                                    case 'pausada': echo 'badge-warning'; break;
                                    case 'completada': echo 'badge-success'; break;
                                    case 'cancelada': echo 'badge-danger'; break;
                                    default: echo 'badge-secondary';
                                }
                                ?> align-middle ml-2">
                                <?php echo ucfirst(str_replace('_', ' ', $produccion['estado'])); ?>
                            </span>
                        </h4>
                        <p>
                            <strong>Orden de Venta:</strong> 
                            <a href="ver_orden.php?id=<?php echo $produccion['id_orden']; ?>" target="_blank">
                                <?php echo $produccion['codigo_orden']; ?>
                            </a>
                        </p>
                        <p><strong>Cliente:</strong> <?php echo $produccion['cliente'] . ' (' . $produccion['ruc'] . ')'; ?></p>
                        <p><strong>Responsable:</strong> <?php echo $produccion['responsable_nombre'] . ' ' . $produccion['responsable_apellido']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6">
                                <p><strong>Fecha Inicio:</strong> <?php echo date('d/m/Y', strtotime($produccion['fecha_inicio'])); ?></p>
                                <p><strong>Fecha Est. Fin:</strong> <?php echo date('d/m/Y', strtotime($produccion['fecha_estimada_fin'])); ?></p>
                                <?php if ($produccion['fecha_fin']): ?>
                                    <p><strong>Fecha Finalización:</strong> <?php echo date('d/m/Y', strtotime($produccion['fecha_fin'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-6">
                                <p><strong>Creado por:</strong> <?php echo $produccion['usuario_nombre'] . ' ' . $produccion['usuario_apellido']; ?></p>
                                <p><strong>Fecha Creación:</strong> <?php echo date('d/m/Y H:i', strtotime($produccion['fecha_creacion'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5>Avance General</h5>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar 
                                <?php 
                                if ($porcentaje_avance >= 100) {
                                    echo 'bg-success';
                                } elseif ($porcentaje_avance >= 75) {
                                    echo 'bg-info';
                                } elseif ($porcentaje_avance >= 50) {
                                    echo 'bg-primary';
                                } elseif ($porcentaje_avance >= 25) {
                                    echo 'bg-warning';
                                } else {
                                    echo 'bg-danger';
                                }
                                ?>" 
                                role="progressbar" 
                                style="width: <?php echo $porcentaje_avance; ?>%" 
                                aria-valuenow="<?php echo $porcentaje_avance; ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                <?php echo $porcentaje_avance; ?>%
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <h6>Cantidad Producida</h6>
                                <h4><?php echo number_format($total_producido); ?> de <?php echo number_format($total_programado); ?></h4>
                            </div>
                            <div class="col-6">
                                <h6>Peso Real</h6>
                                <h4><?php echo number_format($total_peso_real, 2); ?> kg</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Progreso por Etapas</h5>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar 
                                <?php 
                                if ($porcentaje_etapas >= 100) {
                                    echo 'bg-success';
                                } elseif ($porcentaje_etapas >= 75) {
                                    echo 'bg-info';
                                } elseif ($porcentaje_etapas >= 50) {
                                    echo 'bg-primary';
                                } elseif ($porcentaje_etapas >= 25) {
                                    echo 'bg-warning';
                                } else {
                                    echo 'bg-danger';
                                }
                                ?>" 
                                role="progressbar" 
                                style="width: <?php echo $porcentaje_etapas; ?>%" 
                                aria-valuenow="<?php echo $porcentaje_etapas; ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                <?php echo $porcentaje_etapas; ?>%
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <h6>Etapas Completadas</h6>
                                <h4><?php echo $etapas_completadas; ?> de <?php echo $total_etapas; ?></h4>
                            </div>
                            <div class="col-6">
                                <h6>Días Transcurridos</h6>
                                <?php 
                                $dias_transcurridos = floor((time() - strtotime($produccion['fecha_inicio'])) / (60 * 60 * 24));
                                $fecha_estimada = new DateTime($produccion['fecha_estimada_fin']);
                                $fecha_actual = new DateTime();
                                $dias_restantes = $fecha_actual->diff($fecha_estimada)->format("%r%a");
                                ?>
                                <h4><?php echo $dias_transcurridos; ?> 
                                    <?php if ($produccion['estado'] != 'completada' && $produccion['estado'] != 'cancelada'): ?>
                                        <?php if ($dias_restantes < 0): ?>
                                            <span class="badge badge-danger">Retrasada</span>
                                        <?php elseif ($dias_restantes <= 2): ?>
                                            <span class="badge badge-warning">Crítica</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light">
                <div class="row">
                    <div class="col-md-12 text-right">
                        <?php if ($produccion['estado'] == 'programada'): ?>
                            <a href="iniciar_proceso.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-primary" onclick="return confirm('¿Está seguro de iniciar el proceso de producción?');">
                                <i class="fas fa-play"></i> Iniciar Proceso
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($produccion['estado'] == 'en_proceso'): ?>
                            <a href="pausar_proceso.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-warning" onclick="return confirm('¿Está seguro de pausar el proceso de producción?');">
                                <i class="fas fa-pause"></i> Pausar Proceso
                            </a>
                            <a href="completar_produccion.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-success" onclick="return confirm('¿Está seguro de marcar como completada esta producción?');">
                                <i class="fas fa-check"></i> Completar Producción
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($produccion['estado'] == 'pausada'): ?>
                            <a href="reanudar_proceso.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-primary" onclick="return confirm('¿Está seguro de reanudar el proceso de producción?');">
                                <i class="fas fa-play"></i> Reanudar Proceso
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($produccion['estado'] != 'completada' && $produccion['estado'] != 'cancelada'): ?>
                            <a href="registrar_avance.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-info">
                                <i class="fas fa-tasks"></i> Registrar Avance
                            </a>
                            <a href="asignar_rollos.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-secondary">
                                <i class="fas fa-dolly"></i> Asignar Rollos
                            </a>
                            <a href="registrar_desperdicio.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-dark">
                                <i class="fas fa-trash-alt"></i> Registrar Desperdicio
                            </a>
                            <a href="cancelar_produccion.php?id=<?php echo $id_orden_produccion; ?>" class="btn btn-danger" onclick="return confirm('¿Está seguro de cancelar esta producción? Esta acción no se puede deshacer.');">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs para mostrar diferentes secciones -->
<ul class="nav nav-tabs" id="produccionTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="productos-tab" data-toggle="tab" href="#productos" role="tab" aria-controls="productos" aria-selected="true">
            <i class="fas fa-box-open"></i> Productos (<?php echo count($detalles); ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="etapas-tab" data-toggle="tab" href="#etapas" role="tab" aria-controls="etapas" aria-selected="false">
            <i class="fas fa-tasks"></i> Etapas