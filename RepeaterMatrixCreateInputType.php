<?php namespace ProcessWire;

use GraphQL\Type\Definition\InputObjectType;
use ProcessWire\Template;
use ProcessWire\GraphQL\Cache;

require_once(__DIR__ . '/RepeaterMatrixPageCreateInputType.php');

class RepeaterMatrixCreateInputType
{
    public static function &type(Template $template)
    {
        $type =& Cache::type(RepeaterMatrixPageCreateInputType::getName($template), function () use ($template) {
            return new InputObjectType([
        'name' => RepeaterMatrixPageCreateInputType::getName($template),
        'description' => "CreateInputType for pages with template `{$template->name}`.",
        'fields' => self::getFields($template),
      ]);
        });
   
        return $type;
    }

    public static function getFields(Template $template)
    {
        $fields = [];

        // add template fields
        $fields = array_merge($fields, RepeaterMatrixPageCreateInputType::getTemplateFields($template));
    
        // mark required fields as nonNull
        $fields = RepeaterMatrixPageCreateInputType::markRequiredTemplateFields($fields, $template);

        return $fields;
    }
}
