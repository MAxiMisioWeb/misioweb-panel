<?php

namespace Database\Seeders;

use App\Models\RequirementGroup;
use App\Models\RequirementTemplate;
use App\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * Widgets y plantillas de requisitos, tomados del prototipo (panel/prototipo/panel-proyecto.html).
 * Se puede correr las veces que haga falta: actualiza por clave sin duplicar.
 *
 * Precios de EJEMPLO (ver CLAUDE.md): reemplazar por los reales.
 */
class CatalogSeeder extends Seeder
{
    /** key => [nombre, descripción, USD implementación, USD mensual] */
    public const WIDGETS = [
        'blog' => ['Blog', 'Artículos propios con página individual', '30', '3'],
        'calendario' => ['Calendario de actividades', 'Agenda administrable con enlace de inscripción', '30', '4'],
        'reels' => ['Reels de Instagram (ReelsLoop)', 'Reels filtrados por hashtag, con reproducción continua', '40', '5'],
        'eventos' => ['Eventos Misioweb', 'Inscripciones online, un evento por mes de hasta 50 personas', '30', '6'],
        'tienda' => ['Tienda con Mercado Pago', 'Catálogo, carrito, compra y área de usuario', '70', '12'],
        'whatsapp' => ['Botón de WhatsApp', 'Contacto directo desde cualquier página', '10', '1'],
        'chatbot' => ['Chatbot de atención', 'Responde preguntas frecuentes todo el día', '60', '9'],
    ];

    /**
     * Secciones en el orden en que se muestran. widget = null: sección base.
     * Cada campo: [clave, etiqueta, tipo, subgrupo, ayuda].
     */
    public const GROUPS = [
        'sec' => [null, 'Secciones de la portada', 'Tres bloques de actividades. Cada uno necesita título, descripción y foto.', [
            ['s1t', 'Título', 'text', 'Bloque 1'], ['s1d', 'Descripción', 'area', 'Bloque 1'], ['s1f', 'Foto', 'image', 'Bloque 1'],
            ['s2t', 'Título', 'text', 'Bloque 2'], ['s2d', 'Descripción', 'area', 'Bloque 2'], ['s2f', 'Foto', 'image', 'Bloque 2'],
            ['s3t', 'Título', 'text', 'Bloque 3'], ['s3d', 'Descripción', 'area', 'Bloque 3'], ['s3f', 'Foto', 'image', 'Bloque 3'],
        ]],
        'blog' => ['blog', 'Blog', 'Mínimo 2 entradas completas para publicar con contenido real.', [
            ['b1t', 'Título', 'text', 'Entrada 1'], ['b1x', 'Texto completo', 'area', 'Entrada 1'], ['b1i', 'Imagen de portada', 'image', 'Entrada 1'],
            ['b2t', 'Título', 'text', 'Entrada 2'], ['b2x', 'Texto completo', 'area', 'Entrada 2'], ['b2i', 'Imagen de portada', 'image', 'Entrada 2'],
        ]],
        'ig' => ['reels', 'Instagram (ReelsLoop)', 'Muestra los reels filtrados por hashtag. Requiere vincular la cuenta con Meta.', [
            ['igu', 'Usuario de Instagram', 'text'],
            ['igh', 'Hashtags para filtrar los reels', 'text', null, 'Separados por coma. Ej: #yoga, #meditacion'],
            ['igm', 'La cuenta es profesional y está vinculada a una Página de Facebook', 'check', null, 'Hace falta para dar los permisos de Meta'],
            ['iga', 'El cliente nos dio acceso o autorizó la conexión con Meta', 'check'],
            ['igp', 'Plugin ReelsLoop activo en el sitio', 'check', null, 'Lo verifica el plugin MisioWeb Progreso'],
        ]],
        'wc' => ['tienda', 'Tienda (WooCommerce)', 'Catálogo y compra con Mercado Pago. Las claves se cargan directo en WooCommerce, nunca en este panel.', [
            ['wcm', 'Tiene cuenta de Mercado Pago lista para cobrar', 'check'],
            ['wcc', 'Credenciales de Mercado Pago conectadas en WooCommerce', 'check', null, 'Las carga el cliente o las ingresamos juntos en una llamada'],
            ['wce', 'Modalidades de entrega definidas', 'text', null, 'Ej: retiro por el local, envío por correo'],
            ['wcp', 'Cantidad de productos a cargar al inicio', 'text'],
        ]],
        'cal' => ['calendario', 'Calendario de actividades', 'Agenda administrable de actividades propias.', [
            ['calA', 'Actividades con fecha, horario y descripción (mínimo 3)', 'area'],
            ['calL', 'Enlace de inscripción o contacto de cada actividad', 'text'],
        ]],
        'ev' => ['eventos', 'Eventos Misioweb', 'Gestión de eventos e inscripciones en eventos.misioweb.com.', [
            ['evU', 'Responsable que administra los eventos (nombre y email)', 'text'],
            ['evE', 'Primer evento definido (nombre, fecha y cupo)', 'text'],
        ]],
        'wa' => ['whatsapp', 'Botón de WhatsApp', 'Contacto directo desde cualquier página.', [
            ['waN', 'Número de WhatsApp con código de país', 'text'],
            ['waM', 'Mensaje inicial sugerido', 'text'],
        ]],
        'bot' => ['chatbot', 'Chatbot de atención', 'Responde preguntas frecuentes las 24 horas.', [
            ['botF', 'Preguntas frecuentes con sus respuestas', 'area'],
            ['botH', 'Horarios de atención', 'text'],
        ]],
        'mk' => [null, 'Marca y textos legales', 'Elementos que aporta o aprueba el cliente antes de publicar.', [
            ['mkl', 'Logo y elementos de marca entregados', 'check'],
            ['mkp', 'Textos de privacidad y condiciones comerciales aprobados', 'check'],
            ['mkw', 'WhatsApp y datos de contacto', 'text'],
        ]],
    ];

    public function run(): void
    {
        $sort = 0;
        foreach (self::WIDGETS as $key => [$name, $description, $impl, $month]) {
            Widget::updateOrCreate(['key' => $key], [
                'name' => $name,
                'description' => $description,
                'price_usd_impl' => $impl,
                'price_usd_month' => $month,
                'sort' => $sort += 10,
            ]);
        }

        $sort = 0;
        foreach (self::GROUPS as $key => [$widget, $name, $description, $fields]) {
            RequirementGroup::updateOrCreate(['key' => $key], [
                'widget_key' => $widget,
                'name' => $name,
                'description' => $description,
                'sort' => $sort += 10,
            ]);
            foreach ($fields as $i => $f) {
                RequirementTemplate::updateOrCreate(['key' => $f[0]], [
                    'group' => $key,
                    'label' => $f[1],
                    'type' => $f[2],
                    'subgroup' => $f[3] ?? null,
                    'hint' => $f[4] ?? null,
                    'sort' => ($i + 1) * 10,
                ]);
            }
        }
    }
}
