<?php

/**
 * LibreDTE
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero de GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU para
 * obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

// namespace del controlador
namespace website\Dte;

/**
 * Controlador de ventas
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
 * @version 2017-09-11
 */
class Controller_DteVentas extends Controller_Base_Libros
{

    protected $config = [
        'model' => [
            'singular' => 'Venta',
            'plural' => 'Ventas',
        ]
    ]; ///< Configuración para las acciones del controlador

    /**
     * Acción que envía el archivo XML del libro de ventas al SII
     * Si no hay documentos en el período se enviará sin movimientos
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-09-01
     */
    public function enviar_sii($periodo)
    {
        $Emisor = $this->getContribuyente();
        // si el libro fue enviado y no es rectifica error
        $DteVenta = new Model_DteVenta($Emisor->rut, $periodo, (int)$Emisor->config_ambiente_en_certificacion);
        if ($DteVenta->track_id and empty($_POST['CodAutRec']) and $DteVenta->getEstado()!='LRH' and $DteVenta->track_id!=-1) {
            \sowerphp\core\Model_Datasource_Session::message(
                'Libro del período '.$periodo.' ya fue enviado, ahora sólo puede  hacer rectificaciones', 'error'
            );
            $this->redirect(str_replace('enviar_sii', 'ver', $this->request->request));
        }
        // si el periodo es mayor o igual al actual no se puede enviar
        if ($periodo >= date('Ym')) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No puede enviar el libro de ventas del período '.$periodo.', debe esperar al mes siguiente del período', 'error'
            );
            $this->redirect(str_replace('enviar_sii', 'ver', $this->request->request));
        }
        // obtener firma
        $Firma = $Emisor->getFirma($this->Auth->User->id);
        if (!$Firma) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No hay firma electrónica asociada a la empresa (o bien no se pudo cargar), debe agregar su firma antes de generar DTE', 'error'
            );
            $this->redirect('/dte/admin/firma_electronicas');
        }
        // agregar carátula al libro
        $caratula = [
            'RutEmisorLibro' => $Emisor->rut.'-'.$Emisor->dv,
            'RutEnvia' => $Firma->getID(),
            'PeriodoTributario' => substr($periodo, 0, 4).'-'.substr($periodo, 4),
            'FchResol' => $Emisor->config_ambiente_en_certificacion ? $Emisor->config_ambiente_certificacion_fecha : $Emisor->config_ambiente_produccion_fecha,
            'NroResol' =>  $Emisor->config_ambiente_en_certificacion ? 0 : $Emisor->config_ambiente_produccion_numero,
            'TipoOperacion' => 'VENTA',
            'TipoLibro' => 'MENSUAL',
            'TipoEnvio' => 'TOTAL',
        ];
        if (!empty($_POST['CodAutRec'])) {
            $caratula['TipoLibro'] = 'RECTIFICA';
            $caratula['CodAutRec'] = $_POST['CodAutRec'];
        }
        // crear libro
        $Libro = $Emisor->getLibroVentas($periodo);
        $Libro->setCaratula($caratula);
        // se setean resúmenes manuales enviados por post
        if (isset($_POST['TpoDoc'])) {
            $resumen = [];
            $n_tipos = count($_POST['TpoDoc']);
            for ($i=0; $i<$n_tipos; $i++) {
                $cols = [
                    'TpoDoc',
                    'TotDoc',
                    'TotAnulado',
                    'TotOpExe',
                    'TotMntExe',
                    'TotMntNeto',
                    'TotMntIVA',
                    'TotIVAPropio',
                    'TotIVATerceros',
                    'TotLey18211',
                    'TotMntTotal',
                    'TotMntNoFact',
                    'TotMntPeriodo',
                ];
                $row = [];
                foreach ($cols as $col) {
                    if (!empty($_POST[$col][$i])) {
                        $row[$col] = $_POST[$col][$i];
                    }
                }
                $resumen[] = $row;
            }
            $Libro->setResumen($resumen);
        }
        // obtener XML
        $Libro->setFirma($Firma);
        $xml = $Libro->generar();
        if (!$xml) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No fue posible generar el libro de ventas<br/>'.implode('<br/>', \sasco\LibreDTE\Log::readAll()), 'error'
            );
            $this->redirect(str_replace('enviar_sii', 'ver', $this->request->request));
        }
        // enviar al SII sólo si el libro es de un período menor o igual al 201707
        // esto ya que desde 201708 se reemplaza por RCV
        if ($periodo <= 201707) {
            $track_id = $Libro->enviar();
            $revision_estado = null;
            $revision_detalle = null;
            if (!$track_id) {
                \sowerphp\core\Model_Datasource_Session::message(
                    'No fue posible enviar el libro de ventas al SII<br/>'.implode('<br/>', \sasco\LibreDTE\Log::readAll()), 'error'
                );
                $this->redirect(str_replace('enviar_sii', 'ver', $this->request->request));
            }
            \sowerphp\core\Model_Datasource_Session::message(
                'Libro de ventas período '.$periodo.' envíado', 'ok'
            );
        } else {
            $track_id = -1;
            $revision_estado = 'Libro generado';
            $revision_detalle = 'No se envió al SII, ya que se reemplazó por RCV';
            \sowerphp\core\Model_Datasource_Session::message(
                'Libro de ventas período '.$periodo.' generado, pero no se envió al SII, ya que se reemplazó por RCV', 'ok'
            );
        }
        // guardar libro de ventas
        $DteVenta->documentos = $Libro->cantidad();
        $DteVenta->xml = base64_encode($xml);
        $DteVenta->track_id = $track_id;
        $DteVenta->revision_estado = $revision_estado;
        $DteVenta->revision_detalle = $revision_detalle;
        $DteVenta->save();
        $this->redirect(str_replace('enviar_sii', 'ver', $this->request->request));
    }

    /**
     * Acción que genera el archivo CSV con el registro de ventas
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-09-03
     */
    public function descargar_registro_venta($periodo)
    {
        $Emisor = $this->getContribuyente();
        $ventas = $Emisor->getVentas($periodo);
        if (!$ventas) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No hay documentos de venta del período '.$periodo, 'warning'
            );
            $this->redirect(str_replace('descargar_registro_venta', 'ver', $this->request->request));
        }
        foreach ($ventas as &$v) {
            unset($v['anulado']);
        }
        $columnas = Model_DteVenta::$libro_cols;
        unset($columnas['anulado']);
        $columnas['tipo_transaccion'] = 'Tipo Transaccion';
        array_unshift($ventas, $columnas);
        \sowerphp\general\Utility_Spreadsheet_CSV::generate($ventas, 'rv_'.$Emisor->rut.'-'.$Emisor->dv.'_'.$periodo, ';', '');
    }

    /**
     * Acción que genera el archivo CSV con los resúmenes de ventas (ingresados manualmente)
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-09-03
     */
    public function descargar_resumenes($periodo)
    {
        $Emisor = $this->getContribuyente();
        $Libro = new Model_DteVenta($Emisor->rut, (int)$periodo, (int)$Emisor->config_ambiente_en_certificacion);
        if (!$Libro->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'Aun no se ha generado el XML del período '.$periodo, 'error'
            );
            $this->redirect(str_replace('descargar_resumenes', 'ver', $this->request->request));
        }
        $xml = base64_decode($Libro->xml);
        $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta();
        $LibroCompraVenta->loadXML($xml);
        $resumenes = $LibroCompraVenta->getResumenManual() + $LibroCompraVenta->getResumenBoletas();
        if (!$resumenes) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No hay resúmenes para el período '.$periodo, 'warning'
            );
            $this->redirect(str_replace('descargar_resumenes', 'ver', $this->request->request));
        }
        // generar CSV
        $datos = [['Tipo Docto', 'Numero de Doctos', 'Operaciones Exentas', 'Monto Exento', 'Montos Netos', 'Montos de IVA', 'Monto IVA Propio', 'Monto IVA Terceros', 'Ley 18.211', 'Monto Total']];
        foreach ($resumenes as $r) {
            $datos[] = [
                $r['TpoDoc'],
                $r['TotDoc'],
                $r['TotOpExe'],
                $r['TotMntExe'],
                $r['TotMntNeto'],
                $r['TotMntIVA'],
                $r['TotIVAPropio'],
                $r['TotIVATerceros'],
                $r['TotLey18211'],
                $r['TotMntTotal'],
            ];
        }
        \sowerphp\general\Utility_Spreadsheet_CSV::generate($datos, 'rv_resumenes_'.$periodo, ';', '');
    }

    /**
     * Acción que permite obtener el resumen del registro de venta para un período
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-09-10
     */
    public function rcv_resumen($periodo)
    {
        $Emisor = $this->getContribuyente();
        try {
            $resumen = $Emisor->getRCV(['operacion' => 'VENTA', 'periodo' => $periodo, 'estado' => 'REGISTRO', 'detalle'=>false]);
        } catch (\Exception $e) {
            \sowerphp\core\Model_Datasource_Session::message($e->getMessage(), 'error');
            $this->redirect('/dte/dte_ventas/ver/'.$periodo);
        }
        $this->set([
            'Emisor' => $Emisor,
            'periodo' => $periodo,
            'resumen' => $resumen,
        ]);
    }

    /**
     * Acción que permite obtener el detalle del registro de venta para un período
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-09-10
     */
    public function rcv_detalle($periodo, $dte)
    {
        $Emisor = $this->getContribuyente();
        try {
            $detalle = $Emisor->getRCV(['operacion' => 'VENTA', 'periodo' => $periodo, 'dte' => $dte, 'estado' => 'REGISTRO']);
        } catch (\Exception $e) {
            \sowerphp\core\Model_Datasource_Session::message($e->getMessage(), 'error');
            $this->redirect('/dte/dte_ventas/ver/'.$periodo);
        }
        if (!$detalle) {
            \sowerphp\core\Model_Datasource_Session::message('No hay detalle para el período y estado solicitados', 'warning');
            $this->redirect('/dte/dte_ventas/ver/'.$periodo);
        }
        $this->set([
            'Emisor' => $Emisor,
            'periodo' => $periodo,
            'DteTipo' => new \website\Dte\Admin\Mantenedores\Model_DteTipo($dte),
            'detalle' => $detalle,
        ]);
    }

    /**
     * Acción que permite obtener el detalle de documentos emitidos con cierto evento del receptor
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-09-12
     */
    public function eventos_receptor($periodo, $evento)
    {
        $Emisor = $this->getContribuyente();
        $DteVenta = new Model_DteVenta($Emisor->rut, $periodo, (int)$Emisor->config_ambiente_en_certificacion);
        $this->set([
            'Emisor' => $Emisor,
            'periodo' => $periodo,
            'Evento' => (object)['codigo'=>$evento, 'glosa'=>$evento?\sasco\LibreDTE\Sii\RegistroCompraVenta::$eventos[$evento]:'Sin evento registrado'],
            'documentos' => $DteVenta->getDocumentosConEventoReceptor($evento),
        ]);
    }

}
