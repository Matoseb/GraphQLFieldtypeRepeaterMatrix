<?php namespace ProcessWire;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InputObjectType;
use ProcessWire\Page;
use ProcessWire\Field;
use ProcessWire\Template;
use ProcessWire\GraphQL\Utils;

class GraphQLFieldtypeRepeaterMatrix extends WireData implements Module
{
  public static function getModuleInfo()
  {
    return [
      // The module's title, typically a little more descriptive than the class name
      'title' => 'GraphQLFieldtypeRepeaterMatrix',

      // version number
      'version' => '2.0.0',

      // summary is brief description of what this module is
      'summary' => 'GraphQL support for GraphQLFieldtypeRepeaterMatrix.',

      // Optional URL to more information about the module
      'href' => 'https://github.com/Matoseb/GraphQLFieldtypeRepeaterMatrix',

      // Optional font-awesome icon name, minus the 'fa-' part
      // 'icon' => 'map',

      'requires' => ['ProcessGraphQL'],
    ];
  }

  public static function getType(Field $field)
  {
    // bd($field);
    $templateId = $field->get('template_id');
    $template = Utils::templates()->get($templateId);
    $matrixTypes = $field->type->getMatrixTypes($field);
    bd($matrixTypes);
    require_once __DIR__ . '/RepeaterMatrixPageArrayType.php';
    $type = RepeaterMatrixPageArrayType::type($template, $matrixTypes);

    return $type;
  }

  public static function getInputType(Field $field)
  {
    //TODO
    require_once __DIR__ . '/InputfieldRepeaterMatrix.php';
    return InputfieldRepeaterMatrix::type($field);
  }

  public static function setValue(Page $page, Field $field, $value)
  {
    //TODO
  }
}
