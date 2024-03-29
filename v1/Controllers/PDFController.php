<?php
namespace PDFController;

use Dompdf\Dompdf;
use JsonResponse\JsonResponse;
use Luecano\NumeroALetras\NumeroALetras;
use Models\Models;

class PDFController {


    private static function query_factura($id){
        $formatter = new NumeroALetras();
        $query = "SELECT 
        id_factura,nro_factura,fecha_factura,descuento_factura,tipo_factura,orden_compra,valor_moneda_factura, nombre_empresa,ruc_empresa,propietario_empresa,direccion_empresa,categoria_empresa, telefono_empresa,
        nombre_cliente,ruc_cliente,direccion_cliente,timbrado_factura,inicio_timbrado,fin_timbrado,nro_datos_factura,monto_total_factura,logo_url_empresa,obs_empresa_factura,fecha_empresa_factura
        from facturas,clientes,empresa_facturas,empresas,facturas_cajas
        WHERE
        empresa_factura_id = id_empresa_factura and
        id_empresa_empresa = id_empresa and
        id_caja_factura = caja_id_factura and
        id_cliente_factura = id_cliente and
        id_factura = $id";
        
        $query_items = "SELECT nombre_producto,cantidad_producto,precio_producto_factura,porcentaje_impuesto,codigo_producto 
        FROM facturas_items,productos,impuestos 
        WHERE 
        id_impuesto_factura = id_impuesto and
        id_producto_factura = id_producto and
        id_items_factura =  $id";
        
        $items= Models::GET_INTERNO($query_items,"facturas_items");
        $factura = Models::GET_INTERNO($query,"facturas");

        if(!isset($factura[0])){
            return false;
        }

        $df = $factura[0];
        $items_results = $items;

        $items_html = "<table class='tabla tabla_descripcion'><tbody>
                <tr class='descripcion_head' >
                    <td width='3%' >COD.</td>
                    <td width='3%' >CANT.</td>
                    <td width='46%' >DESCRIPCION.</td>
                    <td width='12%'>PRECIO</td>
                    <td width='12%'>EXENTAS</td>
                    <td width='12%'>5%</td>
                    <td width='12%'>10%</td>
                </tr>
                
        ";
        $subtotal0 = 0;
        $subtotal5 = 0; $liqui5 = 0;
        $subtotal10= 0; $liqui10 = 0;
        foreach($items_results as $i){
            $imp = $i['porcentaje_impuesto'];
            $precio = $i['precio_producto_factura'];
            $cant = $i['cantidad_producto'];
            $imp10 = $imp == '10' ? $cant*$precio : 0;
            $imp5= $imp == '5' ? $cant*$precio : 0;
            $imp0= $imp == '0' ? $cant*$precio : 0;
            $subtotal0 += $imp0;
            $subtotal5 += $imp5;
            $subtotal10 += $imp10;

            $add = "<tr>
            <td width='3%'><small>".$i['codigo_producto']."</small></td>
            <td width='3%'><small>".$i['cantidad_producto']."</small></td>
            <td width='46%'><small>".$i['nombre_producto']."</small></td>
            <td width='12%'><small>".number_format($precio,0,',','.')."</small></td>
            <td width='12%'><small>".number_format($imp0,0,',','.')."</small></td>
            <td width='12%'><small>".number_format($imp5,0,',','.')."</small></td>
            <td width='12%'><small>".number_format($imp10,0,',','.')."</small></td>
            </tr>";
            $items_html .= $add;
        }
        $items_html .='</tbody></table>';

        $liqui10 = ($subtotal10 / 11);
        $liqui5 = ($subtotal5 / 22);
        $total_con_descuento = $df['monto_total_factura'] - $df['descuento_factura'];
        $numero_total_letras = $formatter->toWords($total_con_descuento);
        $propietario = $df['propietario_empresa'] == '' ? '' :  'De: '.$df['propietario_empresa'];
        $condicion_venta = $df['tipo_factura'] == '1' ? 'Contado' : 'Credito';

        $trliquidacion_iva = "<tr class='total'>
        <td colspan='2'>
            <small>Liquidación IVA</small>
        </td>
        <td class='text-right'><small>5%: ".number_format($liqui5,1,',','.')."</small></td>
        <td class='text-right'><small>10%: ".number_format($liqui10,1,',','.')." </small></td>
        <td class='text-right' colspan='2'><small>Total IVA: ".number_format(($liqui10 + $liqui5),1,',','.')." </small></td>
        </tr>";

        $html = "<html>
        <head>
        <title>factura</title>
        <style>*{font-family:monospace;margin:0;padding:0;box-sizing:border-box} .tabla_descripcion{text-align:center} .tabla_descripcion tr td{vertical-align: top;font-size:12px;} .tabla{width:100%;font-size:11px;}.text-center{text-align:center}.text-right{text-align:right}.w-100{width:100%}.bg-smoke{background-color:#f5f5f5}.border-trl{border-top:1px solid #000;border-right:1px solid #000;border-left:1px solid #000}.container{width:204mm;margin:2mm auto 0}.cabezera{width:100%}.cabezera .titulos{width:65%;padding:8px;border-right:1px solid silver}.cabezera .datos{text-align:center;background-color:#f5f5f5;padding:8px}.datos_cliente{width:100%;border:1px solid silver}.datos_cliente .cliente{text-align:left;padding:8px;border-right:1px solid silver}.datos_cliente .factura{text-align:center;padding:8px}.items{width:100%;border-right:1px solid silver;border-left:1px solid silver;}.descripcion_head td{padding:2px;background-color:silver;text-align:center;border-bottom:1px solid silver}.items .item td{font-weight:lighter;padding:0 3px 1px 1px;text-align:center}.subtotales td{padding:5px;border-top:1px solid silver}.total td{border-top:1px solid silver;padding:5px}.datos_graficos{width:100%;border:1px solid #000;font-size:10px;}.datos_graficos .grafica{width:65%;padding:4px;}.datos_graficos .fiscales{padding:4px;border-left:1px solid silver}</style>
        </head>
        <body>
        <div class='container'>
            <table class='tabla cabezera border-trl'>
            <tbody>
                <tr>
                    <td class='titulos bg-smoke text-center'>
                        <h3>".$df['nombre_empresa']."</h3>
                        <h5>".$propietario."</h5>
                        <small>".$df['categoria_empresa']."</small><br />
                        <small>".$df['direccion_empresa']." Tel:".$df['telefono_empresa']."</small>
                    </td>
                    <td class='datos bg-smoke text-center'>
                        <h5>Timbrado nº: ".$df['timbrado_factura']."</h5>
                        <h5>RUC: ".$df['ruc_empresa']."</h5>
                        <h6>Inicio vigencia: ".$df['inicio_timbrado']."</h6>
                        <h6>Fin vigencia: ".$df['fin_timbrado']."</h6>
                        <h4>Factura nº: ".$df['nro_datos_factura']."-".sprintf("%07d", $df['nro_factura'])." </h4>
                    </td>
                </tr>
                </tbody>
            </table>
        
            <table class='tabla datos_cliente'>
                <tbody>
                <tr>
                    <td class='cliente'>
                        <h5>Fecha emisión: ".$df['fecha_factura']."</h5>
                        <h5>NOMBRE O RAZON SOCIAL: ".$df['nombre_cliente']."</h5>
                        <h5>RUC O CI: ".$df['ruc_cliente']."</h5>
                        <h5>DIRECCION: ".$df['direccion_cliente']."</h5>
                    </td>
                    <td class='factura'>
                        <h5>Cond. de venta:".$condicion_venta."</h5>
                    </td>
                </tr>
                </tbody>
            </table>
        
                
                ".$items_html."
                <table class='tabla'>
                <tbody>
               <tr><td colspan='6'><br/></td></tr>
                <tr class='subtotales bg-smoke'>
                    <td colspan='3'>
                        SUBTOTAL:
                    </td>
                    <td class='text-right'> <small>".number_format($subtotal0,0,',','.')."</small> </td>
                    <td class='text-right'> <small>".number_format($subtotal5,0,',','.')."</small> </td>
                    <td class='text-right'> <small>". number_format($subtotal10,0,',','.')."</small> </td>
                </tr>
                <tr class='total'>
                    <td colspan='3'>
                        DESCUENTO: 
                    </td>
                    <td class='text-right' colspan='3'> ".number_format($df['descuento_factura'], 1, ',', '.')." </td>
                </tr>

                $trliquidacion_iva

                <tr class='total'>
                    <td colspan='4'>
                        <small>Total: ".$numero_total_letras."</small>
                    </td>
                    <td class='text-right' colspan='2'>".number_format($total_con_descuento, 0, ',', '.')."</td>
                </tr>
                </tbody>
            </table>
        
            <table class='datos_graficos'>
                <tbody>
                <tr>
                    <td class='grafica' valign='top'>
                        <small>".$df['obs_empresa_factura']." ".$df['fecha_empresa_factura']."</small>
                    </td>
                    <td class='fiscales'>
                        <small>Original: Cliente</small><br/>
                        <small>Duplicado: Archivo tributario</small><br/>
                        <small>Triplicado: Contabilidad</small>
                    </td>
                </tr>
                </tbody>
            </table>".'
        </div>
        </body>
        </html>';
        $array = [
            'html'=>$html,
            'factura'=>$factura,
        ];
        return $array;
    }


    public static function view($array){
        
        $id = $array["id"];

        $factura = self::query_factura($id);
        if($factura){
            echo $factura['html'];
        }
        else{
            return JsonResponse::jsonResponseError(false,200,'Not found');
        }
    }





    public static function factura($array){

        $dompdf = new Dompdf();
        
        $id = $array["id"];

        $factura = self::query_factura($id);

        $df = ($factura['factura'][0]);
        
        $dompdf->loadHtml($factura['html']);
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();
        $dompdf->stream($df['fecha_factura']."_".$df['nro_factura']); 
    }

    
}