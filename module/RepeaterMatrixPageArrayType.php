<?php namespace ProcessWire;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use ProcessWire\Template;
use ProcessWire\GraphQL\Cache;
use ProcessWire\GraphQL\Type\SelectorType;
use ProcessWire\GraphQL\Type\PageArrayType;
use ProcessWire\GraphQL\Utils;
use ProcessWire\NullPage;

require_once __DIR__ . '/RepeaterMatrixPageType.php';

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
    } else {
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

    bd($matrixTypes);

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
