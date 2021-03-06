<ul class="nav nav-pills pull-right">
<?php if (!$Emisor->config_ambiente_en_certificacion) : ?>
    <li>
        <a href="https://www4.sii.cl/consdcvinternetui/#/home" title="Ir al registro de compra y venta en el SII" target="_blank">
            <span class="fa fa-bank"></span>
            Ver RCV en SII
        </a>
    </li>
<?php endif; ?>
    <li>
        <a href="<?=$_base?>/dte/dte_emitidos/buscar" title="Búsqueda avanzada de documentos emitidos">
            <span class="fa fa-search"></span> Buscar
        </a>
    </li>
    <li>
        <a href="<?=$_base?>/dte/dte_compras" title="Ir al libro de compras">
            <span class="fa fa-book"></span> Libro de compras
        </a>
    </li>
</ul>
<h1>Libro de ventas (IEV)</h1>
<?php
foreach ($periodos as &$p) {
    $acciones = '<a href="dte_ventas/ver/'.$p['periodo'].'" title="Ver estado del libro del período"><span class="fa fa-search btn btn-default"></span></a>';
    if ($p['emitidos'])
        $acciones .= ' <a href="dte_ventas/csv/'.$p['periodo'].'" title="Descargar CSV del libro del período"><span class="fa fa-file-excel-o btn btn-default"></span></a>';
    else
        $acciones .= ' <span class="fa fa-file-excel-o btn btn-default disabled"></span>';
    $p[] = $acciones;
}
array_unshift($periodos, ['Período','Emitidos', 'Envíados', 'Track ID', 'Estado', 'Acciones']);
new \sowerphp\general\View_Helper_Table($periodos);
?>
<a class="btn btn-primary btn-lg btn-block" href="<?=$_base?>/dte/dte_ventas/sin_movimientos" role="button">Enviar libro de ventas sin movimientos</a>
