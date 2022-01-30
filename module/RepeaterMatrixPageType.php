<?php namespace ProcessWire;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use ProcessWire\Page;
use ProcessWire\NullPage;
use ProcessWire\Template;
use ProcessWire\WireData;
use ProcessWire\GraphQL\Cache;
use ProcessWire\GraphQL\Permissions;
use ProcessWire\GraphQL\Utils;
use ProcessWire\GraphQL\Type\UserType;
use ProcessWire\GraphQL\Type\PageType;
use ProcessWire\GraphQL\Type\PageArrayType;
use ProcessWire\GraphQL\Type\SelectorType;

// require_once(__DIR__ . '/RepeaterMatrixPageArrayType.php');

class RepeaterMatrixPageType
{
  private static $emptyUser;

  public static function &type(Template $template, $matrixType = null)
  {
    $type = null;
    if ($matrixType != null) {
      $type = &self::templateType($template, $matrixType);
    } else {
      $type = &Cache::type(self::getName($template), function () use (
        &$type,
        $template
      ) {
        return new InterfaceType([
          'name' => self::getName($template),
          'description' => self::getDescription($template),
          'fields' => function () use ($template) {
            $fields = PageType::getLegalBuiltInFields();
            $fields[] = [
              'name' => 'repeater_matrix_type',
              'description' => 'Repeater Matrix Type ID',
              'type' => Type::int(),
            ];
            return $fields;
          },
          'resolveType' => function ($value) use (&$type, $template) {
            $resolvedType = $type;

            $matrixType = $value->repeater_matrix_type;

            if ($matrixType && $value->template instanceof Template) {
              $resolvedType = &self::templateType($template, $matrixType);
            }

            return $resolvedType;
          },
        ]);
      });
    }

    return $type;
  }

  public static function &templateType(Template $template, $matrixType)
  {
    $templateType = &Cache::type(
      self::getName($template, $matrixType),
      function () use ($template, $matrixType) {
        $type = &self::type($template);
        return new ObjectType([
          'name' => self::getName($template, $matrixType),
          'description' => self::getDescription($template, $matrixType),
          'fields' => function () use ($template, $matrixType) {
            return self::getFields($template, $matrixType);
          },
          'interfaces' => [$type],
        ]);
      }
    );

    return $templateType;
  }

  public static function getFields(Template $template, $matrixType)
  {
    $fields = [];

    // add the template fields
    $legalFields = Utils::module()->legalFields;

    foreach ($template->fields as $field) {
      $field = $template->fieldgroup->getFieldContext(
        $field->id,
        'matrix' . $matrixType
      );

      // skip illigal fields
      if (!in_array($field->name, $legalFields)) {
        continue;
      }

      // check if user has permission to view this field
      if (
        !Permissions::canViewField($field, $template) &&
        $field->name !== 'repeater_matrix_type'
      ) {
        continue;
      }

      $fieldClass = Utils::pwFieldToGraphqlClass($field);
      if (is_null($fieldClass)) {
        continue;
      }

      $fieldSettings = $fieldClass::field($field);
      if ($field->required) {
        $fieldSettings['type'] = Type::nonNull($fieldSettings['type']);
      }
      $fields[] = $fieldSettings;
    }

    // add all the built in page fields
    foreach (self::type($template)->getFields() as $field) {
      $fields[] = $field;
    }

    return $fields;
  }

  public static function getName(Template $template, $matrixType = null)
  {
    return Utils::normalizeTypeName("{$template->name}Page") . $matrixType;
  }

  public static function getDescription(Template $template, $matrixType = null)
  {
    $desc = $template->description;
    if ($desc) {
      return $desc;
    }
    return 'RepeaterMatrixPageType with template `' .
      $template->name .
      $matrixType .
      '`.';
  }
}
