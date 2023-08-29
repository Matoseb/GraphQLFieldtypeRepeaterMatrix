<?php namespace ProcessWire\GraphQL\Type\Fieldtype;

// ! BETA VERSION -- GRAPHQL MODULE POSSIBLY COMING

use ProcessWire\Page;
use ProcessWire\NullPage;
use ProcessWire\Selectors;
use ProcessWire\Template;

use ProcessWire\GraphQL\Cache;
use ProcessWire\GraphQL\Utils;
use ProcessWire\GraphQL\Permissions;

use ProcessWire\GraphQL\Type\UserType;
use ProcessWire\GraphQL\Type\PageType;
use ProcessWire\GraphQL\Type\PageArrayType;
use ProcessWire\GraphQL\Type\SelectorType;

use ProcessWire\GraphQL\Type\Fieldtype\Traits\InputFieldTrait;
use ProcessWire\GraphQL\Type\Fieldtype\Traits\SetValueTrait;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ResolveInfo;


class FieldtypeRepeaterMatrix
{ 
  use InputFieldTrait;
  use SetValueTrait;

  public static $name = 'RepeaterMatrix';

  public static $description = 'Maintains a collection of fields that are repeated for any number of times.';

  public static function type($field)
  {

    $templateId = $field->get('template_id');
    $template = Utils::templates()->get($templateId);
    $matrixTypes = $field->type->getMatrixTypes($field);
    $type = RepeaterMatrixPageArrayType::type($template, $matrixTypes);

    return $type;
  }

  public static function &field($field)
  {
    $field =& Cache::field($field->name, function () use ($field) {
      // description
      $desc = $field->description;
      if (!$desc) {
        $desc = "Field with the type of {$field->type}";
      }

      return [
        'name' => $field->name,
        'description' => $desc,
        'type' => self::type($field),
        'args' => [
          's' => [
            'type' => SelectorType::type(),
            'description' => "ProcessWire selector."
          ],
        ],
        'resolve' => function (Page $page, array $args) use ($field) {
          $fieldName = $field->name;
          $selector = "";
          if (isset($args['s'])) {
            $selector = $args['s'];
          }
          $selector = new Selectors($selector);
          $result = $page->$fieldName->find((string) $selector);
          if ($result instanceof NullPage) return null;
          return $result;
        }
      ];
    });

    return $field;
  }
  
  public static function inputField($field)
  {
    return InputfieldRepeaterMatrix::inputField($field);
  }
  
  public static function setValue(Page $page, $field, $value)
  {
    return InputfieldRepeaterMatrix::setValue($page, $field, $value);
  }
}


//! -- RepeaterMatrixPageType -------------------------------------------------------------------------------------------------

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
            //! overwrites page name field
			      $fields[] = [
              'name' => 'name',
              'description' => 'Repeater Matrix Name',
              'type' => Type::string(),
			        'resolve' => function ($value) {
                return $value->matrix("name");
              }
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


//! -- RepeaterMatrixPageArrayType -------------------------------------------------------------------------------------------------

class RepeaterMatrixPageArrayType
{
  public static function &type(
    Template $template,
    $matrixTypes,
    $specificMatrixType = null
  ) {
    $type = null;
    if ($specificMatrixType != null) {
      $type = &self::templateType($template, $specificMatrixType);
    } else if($template != null) {
      $type = &Cache::type(self::getName($template), function () use (
        $template,
        $matrixTypes
      ) {
        $types = self::getMatrixPageTypes($template, $matrixTypes);

        return new ObjectType([
          'name' => self::getName($template),
          'description' => self::getDescription($template),
          'fields' => array_merge(
            $types,
            PageArrayType::getPaginationFields(),
            [
              [
                'name' => 'list',
                'type' => Type::listOf(RepeaterMatrixPageType::type($template)),
                'description' => 'List of PW Pages.',
                'resolve' => function (
                  $value,
                  $args,
                  $context,
                  ResolveInfo $info
                ) use ($template) {
                  return $value;
                },
              ],
              [
                'name' => 'first',
                'type' => RepeaterMatrixPageType::type($template),
                'description' => 'Returns the first item in the WireArray.',
                'resolve' => function ($value) use ($template) {
                  $first = $value->first();
                  if ($first) {
                    return $first;
                  }
                  return null;
                },
              ],
              [
                'name' => 'last',
                'type' => RepeaterMatrixPageType::type($template),
                'description' => 'Returns the last item in the WireArray.',
                'resolve' => function ($value) {
                  $last = $value->last();
                  if ($last) {
                    return $last;
                  }
                  return null;
                },
              ],
            ]
          ),
        ]);
      });
    } else {
      
    }
    // }
    return $type;
  }

  public static function getName(Template $template, $matrixType = null)
  {
    return Utils::normalizeTypeName($template->name . 'Page' . $matrixType) .
      'Array';
  }

  public static function getDescription(Template $template, $matrixType = null)
  {
    $desc = $template->description;
    if ($desc) {
      return $desc;
    }
    return 'MatrixPageArray with template `' . $template->name . '`.';
  }

  public static function field(Template $template, $matrixType = null)
  {
    $type = &self::type($template, $matrixType);
    return [
      'name' => Utils::normalizeFieldName($template->name, $matrixType),
      'description' => self::getDescription($template, $matrixType),
      'type' => $type,
      'args' => [
        's' => [
          'type' => SelectorType::type($template, $matrixType),
          'description' => 'ProcessWire selector.',
        ],
      ],
      'resolve' => function (
        $pages,
        array $args,
        $context,
        ResolveInfo $info
      ) use ($template, $matrixType) {
        $finderOptions = self::getFinderOptions($info);
        $selector = '';

        if ($template) {
          $selector .= "template=$template, ";
        }
        if (isset($args['s'])) {
          $selector .= $args['s'] . ', ';
        }
        rtrim($selector, ', ');
        return $pages->find(
          SelectorType::parseValue($selector),
          $finderOptions
        );
      },
    ];
  }

  public static function &templateType(Template $template, $matrixType)
  {
    $type = &Cache::type(
      self::getName($template, $matrixType),
      function () use ($template, $matrixType) {
        return new ObjectType([
          'name' => self::getName($template, $matrixType),
          'description' => self::getDescription($template, $matrixType),
          'fields' => array_merge(PageArrayType::getPaginationFields(), [
            [
              'name' => 'list',
              'type' => Type::listOf(
                RepeaterMatrixPageType::type($template, $matrixType)
              ),
              'description' =>
                'List of ' . self::getName($template, $matrixType),
              'resolve' => function ($value) {
                return $value;
              },
            ],
            [
              'name' => 'first',
              'type' => RepeaterMatrixPageType::type($template, $matrixType),
              'description' => 'Returns the first item in the WireArray.',
              'resolve' => function ($value) {
                $first = $value->first();
                if ($first) {
                  return $first;
                }
                return null;
              },
            ],
            [
              'name' => 'last',
              'type' => RepeaterMatrixPageType::type($template, $matrixType),
              'description' => 'Returns the last item in the WireArray.',
              'resolve' => function ($value) {
                $last = $value->last();
                if ($last) {
                  return $last;
                }
                return null;
              },
            ],
          ]),
        ]);
      }
    );
    return $type;
  }

  public static function getMatrixPageTypes(Template $template, $matrixTypes)
  {
    $types = [];

    foreach ($matrixTypes as $name => $matrixType) {
      $types[] = [
        'name' => '_' . $name,
        'description' => 'Array of type ' . $matrixType,
        'type' => self::type($template, $matrixTypes, $matrixType),
        'resolve' => function ($value) {
          return $value;
        },
      ];
    }

    return $types;
  }
}








//! -- InputfieldRepeaterMatrix -------------------------------------------------------------------------------------------------
class InputfieldRepeaterMatrix
{
  public static function getName()
  {
    return 'InputfieldRepeaterMatrix';
  }

  public static function getDescription($field = null)
  {
    $desc = '';
    if (!is_null($field)) {
      $desc = $field->description;
    }
    return $desc;
  }
  
  public static function type($field)
  {
    return Cache::type(self::getName(), function () use ($field) {
      return new InputObjectType([
        'name' => self::getName(),
        'fields' => [
          'add' => [
            'type' => Type::listOf(Type::id()),
            'description' => 'List of page ids that you would like to add.',
          ],
          'remove' => [
            'type' => Type::listOf(Type::id()),
            'description' => 'List of page ids that you would like to remove.',
          ]
        ],
      ]);
    });
  }

  public static function inputField($field)
  {
    return [
      'name' => $field->name,
      'description' => self::getDescription($field),
      'type' => self::type($field),
    ];
  }

  public static function setValue(Page $page, $field, $value)
  {
    // TODO
  }
}
