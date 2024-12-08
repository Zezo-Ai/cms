<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Event;
use craft\base\Field;
use craft\base\InlineEditableFieldInterface;
use craft\base\MergeableFieldInterface;
use craft\base\RelationalFieldInterface;
use craft\base\RelationalFieldTrait;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry as EntryElement;
use craft\events\RegisterComponentTypesEvent;
use craft\fields\conditions\TextFieldConditionRule;
use craft\fields\data\LinkData;
use craft\fields\linktypes\Asset;
use craft\fields\linktypes\BaseLinkType;
use craft\fields\linktypes\BaseTextLinkType;
use craft\fields\linktypes\Category;
use craft\fields\linktypes\Email as EmailType;
use craft\fields\linktypes\Entry;
use craft\fields\linktypes\Phone;
use craft\fields\linktypes\Url as UrlType;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\generators\LinkDataType;
use craft\helpers\ArrayHelper;
use craft\helpers\Component;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\validators\ArrayValidator;
use craft\validators\StringValidator;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use yii\base\InvalidArgumentException;
use yii\db\Schema;

/**
 * Link represents a Link field.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class Link extends Field implements InlineEditableFieldInterface, RelationalFieldInterface, MergeableFieldInterface
{
    use RelationalFieldTrait;

    /**
     * @event RegisterComponentTypesEvent The event that is triggered when registering the link types for Link fields.
     * @see types()
     */
    public const EVENT_REGISTER_LINK_TYPES = 'registerLinkTypes';

    /** @deprecated in 5.3.0 */
    public const TYPE_URL = 'url';
    /** @deprecated in 5.3.0 */
    public const TYPE_TEL = 'tel';
    /** @deprecated in 5.3.0 */
    public const TYPE_EMAIL = 'email';

    private static array $_types;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Link');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return 'link';
    }

    /**
     * @inheritdoc
     */
    public static function phpType(): string
    {
        return sprintf('\\%s|null', LinkData::class);
    }

    /**
     * @inheritdoc
     */
    public static function dbType(): array
    {
        return [
            'value' => Schema::TYPE_STRING,
            'type' => Schema::TYPE_STRING,
            'label' => Schema::TYPE_STRING,
            'urlSuffix' => Schema::TYPE_STRING,
            'target' => Schema::TYPE_STRING,
            'title' => Schema::TYPE_STRING,
            'class' => Schema::TYPE_STRING,
            'id' => Schema::TYPE_STRING,
            'rel' => Schema::TYPE_STRING,
            'ariaLabel' => Schema::TYPE_STRING,
        ];
    }

    /**
     * @return array<string,class-string<BaseLinkType>>
     */
    private static function types(): array
    {
        if (!isset(self::$_types)) {
            /** @var class-string<BaseLinkType>[] $types */
            $types = [
                Asset::class,
                Category::class,
                EmailType::class,
                Entry::class,
                Phone::class,
            ];

            // Fire a registerLinkTypes event
            if (Event::hasHandlers(self::class, self::EVENT_REGISTER_LINK_TYPES)) {
                $event = new RegisterComponentTypesEvent([
                    'types' => $types,
                ]);
                Event::trigger(self::class, self::EVENT_REGISTER_LINK_TYPES, $event);
                $types = $event->types;
            }

            // URL *has* to be there
            $types[] = UrlType::class;

            self::$_types = array_combine(
                array_map(fn(string $type) => $type::id(), $types),
                $types,
            );
        }

        return self::$_types;
    }

    /**
     * @var bool Whether the Label field should be shown.
     * @since 5.5.0
     */
    public bool $showLabelField = false;

    /**
     * @var string[] Attribute fields to show.
     * @phpstan-var array<'urlSuffix'|'target'|'title'|'class'|'id'|'rel'|'ariaLabel'>
     * @since 5.6.0
     */
    public array $advancedFields = [];

    /**
     * @var array<string,BaseLinkType>
     * @see getLinkTypes())
     */
    private array $_linkTypes;

    /**
     * @var string[] Allowed link types
     */
    public array $types = [
        'entry',
        'url',
    ];

    /**
     * @var array<string,array> Settings for the allowed types
     */
    public array $typeSettings = [];

    /**
     * @var int The maximum length (in bytes) the field can hold
     */
    public int $maxLength = 255;

    /**
     * @var bool Whether GraphQL values should be returned as objects with `type`,
     * `value`, `label`, `urlSuffix`, and `url` keys.
     */
    public bool $fullGraphqlData = true;

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        if (isset($config['types'], $config['typeSettings'])) {
            // Filter out any unneeded type settings
            foreach (array_keys($config['typeSettings']) as $typeId) {
                if (!in_array($typeId, $config['types'])) {
                    unset($config['typeSettings'][$typeId]);
                }
            }
        }

        if (array_key_exists('placeholder', $config)) {
            unset($config['placeholder']);
        }

        $config['advancedFields'] ??= [];

        if (isset($config['showTargetField'])) {
            unset($config['showTargetField']);
            $config['advancedFields'][] = 'target';
        }

        if (isset($config['showUrlSuffixField'])) {
            unset($config['showUrlSuffixField']);
            $config['advancedFields'][] = 'urlSuffix';
        }

        if (isset($config['graphqlMode'])) {
            $config['fullGraphqlData'] = ArrayHelper::remove($config, 'graphqlMode') === 'full';
        }

        // Default fullGraphqlData to false for existing fields
        if (isset($config['id']) && !isset($config['fullGraphqlData'])) {
            $config['fullGraphqlData'] = false;
        }

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['placeholder']);
        return $fields;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['types'], ArrayValidator::class];
        $rules[] = [['types', 'maxLength'], 'required'];
        $rules[] = [['maxLength'], 'number', 'integerOnly' => true, 'min' => 10];
        return $rules;
    }

    /**
     * @deprecated in 5.6.0
     * @return bool
     */
    public function getShowTargetField(): bool
    {
        return in_array('target', $this->advancedFields);
    }

    /**
     * @deprecated in 5.6.0
     */
    public function setShowTargetField(bool $showTargetField): void
    {
        if (!$this->getShowTargetField()) {
            $this->advancedFields[] = 'target';
        }
    }

    /**
     * Returns the link types available to the field.
     *
     * @return array<string,BaseLinkType>
     */
    public function getLinkTypes(): array
    {
        if (!isset($this->_linkTypes)) {
            $this->_linkTypes = [];
            $types = self::types();

            foreach ($this->types as $typeId) {
                if (isset($types[$typeId])) {
                    $this->_linkTypes[$typeId] = Component::createComponent([
                        'type' => $types[$typeId],
                        'settings' => $this->typeSettings[$typeId] ?? [],
                    ], BaseLinkType::class);
                }
            }
        }

        return $this->_linkTypes;
    }

    private function resolveType(string $value): string
    {
        $linkTypes = $this->getLinkTypes();

        // check URL last, if it's selected
        $urlType = ArrayHelper::remove($linkTypes, UrlType::id());
        if ($urlType) {
            $linkTypes[UrlType::id()] = $urlType;
        }

        foreach ($linkTypes as $id => $linkType) {
            if ($linkType->supports($value)) {
                return $id;
            }
        }

        // See if any unselected types support it
        foreach (self::types() as $typeId => $type) {
            if (!isset($linkTypes[$typeId]) && $type !== UrlType::class) {
                $linkType = Component::createComponent($type, BaseLinkType::class);
                if ($linkType->supports($value)) {
                    return $linkType::id();
                }
            }
        }

        return UrlType::id();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        // Sort types by the order from the config and if anything remains by the label, with URL at the top
        // get only the selected types
        /** @var Collection<string,class-string<BaseLinkType>> $selectedTypes */
        $selectedTypes = Collection::make();
        foreach (self::types() as $typeId => $type) {
            if (in_array($typeId, $this->types)) {
                $selectedTypes[$typeId] = $type;
            }
        }

        // now get the remaining types (if there are any)
        $remainingTypes = Collection::make();
        if ($selectedTypes->count() < count(self::types())) {
            $remainingTypes = Collection::make(self::types())
                ->filter(function($value, $key) use ($selectedTypes) {
                    return !isset($selectedTypes[$key]);
                })
                // and sort them by label, with URL at the top
                ->sort(function(string $a, string $b) {
                    /** @var class-string<BaseLinkType> $a */
                    /** @var class-string<BaseLinkType> $b */
                    if ($a === UrlType::class) {
                        return -1;
                    }
                    if ($b === UrlType::class) {
                        return 1;
                    }
                    return $a::displayName() <=> $b::displayName();
                });
        }

        // combine both array of types
        $types = $selectedTypes->merge($remainingTypes);

        $linkTypeOptions = $types->map(fn(string $type) => [
            'label' => $type::displayName(),
            'value' => $type::id(),
        ])->all();

        $html = Cp::checkboxSelectFieldHtml([
            'label' => Craft::t('app', 'Allowed Link Types'),
            'id' => 'types',
            'fieldClass' => 'mb-0',
            'name' => 'types',
            'options' => $linkTypeOptions,
            'values' => $this->types,
            'required' => true,
            'targetPrefix' => 'types-',
            'sortable' => true,
        ]);

        $linkTypes = $this->getLinkTypes();
        $view = Craft::$app->getView();

        foreach ($types->all() as $typeId => $typeClass) {
            $linkType = $linkTypes[$typeId] ?? Component::createComponent($typeClass, BaseLinkType::class);
            $typeSettingsHtml = $view->namespaceInputs(fn() => $linkType->getSettingsHtml(), "typeSettings[$typeId]");
            if ($typeSettingsHtml) {
                $html .=
                    Html::beginTag('div', [
                        'id' => "types-$typeId",
                        'class' => array_keys(array_filter([
                            'pt-xl' => true,
                            'hidden' => !isset($linkTypes[$typeId]),
                        ])),
                    ]) .
                    Html::tag('hr') .
                    $typeSettingsHtml .
                    Html::endTag('div');
            }
        }

        $html .=
            Html::tag('hr') .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Show the “Label” field'),
                'id' => 'show-label-field',
                'name' => 'showLabelField',
                'on' => $this->showLabelField,
            ]) .
            Cp::checkboxSelectFieldHtml([
                'label' => Craft::t('app', 'Advanced Fields'),
                'id' => 'attribute-fields',
                'name' => 'advancedFields',
                'options' => [
                    ['label' => Craft::t('app', 'URL Suffix'), 'value' => 'urlSuffix'],
                    ['label' => Craft::t('app', 'Target'), 'value' => 'target'],
                    ['label' => Craft::t('app', 'Title Text'), 'value' => 'title'],
                    ['label' => Craft::t('app', 'Class Name'), 'value' => 'class'],
                    ['label' => Craft::t('app', 'ID'), 'value' => 'id'],
                    ['label' => Template::raw(Craft::t('app', 'Relation ({ex})', ['ex' => '<code>rel</code>'])), 'value' => 'rel'],
                    ['label' => Craft::t('app', 'ARIA Label'), 'value' => 'ariaLabel'],
                ],
                'values' => $this->advancedFields,
                'sortable' => true,
            ]) .
            Html::tag('hr') .
            Html::button(Craft::t('app', 'Advanced'), options: [
                'class' => 'fieldtoggle',
                'data' => ['target' => 'advanced'],
            ]) .
            Html::beginTag('div', [
                'id' => 'advanced',
                'class' => 'hidden',
            ]) .
            Cp::textFieldHtml([
                'label' => Craft::t('app', 'Max Length'),
                'instructions' => Craft::t('app', 'The maximum length (in bytes) the field can hold.'),
                'id' => 'maxLength',
                'name' => 'maxLength',
                'type' => 'number',
                'min' => '10',
                'step' => '10',
                'value' => $this->maxLength,
                'errors' => $this->getErrors('maxLength'),
                'data' => ['error-key' => 'maxLength'],
            ]);

        if (Craft::$app->getConfig()->getGeneral()->enableGql) {
            $html .=
                Cp::selectFieldHtml([
                    'label' => Craft::t('app', 'GraphQL Mode'),
                    'id' => 'graphql-mode',
                    'name' => 'graphqlMode',
                    'options' => [
                        ['label' => Craft::t('app', 'Full data'), 'value' => 'full'],
                        ['label' => Craft::t('app', 'URL only'), 'value' => 'url'],
                    ],
                    'value' => $this->fullGraphqlData ? 'full' : 'url',
                ]);
        }

        $html .= Html::endTag('div');

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        // if this was set due to propagateAll for a fresh element (as opposed to the translation method),
        // and an element is selected, swap it with the same element in the current site (if it exists)
        if (
            $value instanceof LinkData &&
            $element?->propagating &&
            $element->propagateAll &&
            isset($element->propagatingFrom) &&
            $this->getTranslationKey($element) !== $this->getTranslationKey($element->propagatingFrom)
        ) {
            $linkedElement = $value->getElement();
            if ($linkedElement && $linkedElement::isLocalized()) {
                $localizedQuery = $linkedElement->getLocalized();
                if (
                    $localizedQuery instanceof ElementQueryInterface &&
                    $localizedQuery->siteId($element->siteId)->exists()
                ) {
                    $type = $value->getType();
                    $value = [
                        'type' => $type,
                        'value' => sprintf('{%s:%s@%s:url}', $linkedElement::refHandle(), $linkedElement->id, $element->siteId),
                    ];
                }
            }
        }

        if ($value instanceof LinkData) {
            return $value;
        }

        $linkTypes = $this->getLinkTypes();

        if (is_array($value)) {
            $typeId = $value['type'] ?? UrlType::id();
            $config = array_filter([
                'label' => (isset($value['label']) && $this->showLabelField) ? $value['label'] : null,
                'urlSuffix' => (isset($value['urlSuffix']) && in_array('urlSuffix', $this->advancedFields)) ? $value['urlSuffix'] : null,
                'target' => (isset($value['target']) && in_array('target', $this->advancedFields)) ? $value['target'] : null,
                'title' => (isset($value['title']) && in_array('title', $this->advancedFields)) ? $value['title'] : null,
                'class' => (isset($value['class']) && in_array('class', $this->advancedFields))
                    ? (implode(' ', array_map(fn(string $class) => Html::id($class), explode(' ', $value['class']))))
                    : null,
                'id' => (isset($value['id']) && in_array('id', $this->advancedFields)) ? Html::id($value['id']) : null,
                'rel' => (isset($value['rel']) && in_array('rel', $this->advancedFields))
                    ? (implode(' ', array_map(fn(string $class) => Html::id($class), explode(' ', $value['rel']))))
                    : null,
                'ariaLabel' => (isset($value['ariaLabel']) && in_array('ariaLabel', $this->advancedFields)) ? $value['ariaLabel'] : null,
            ]);
            $value = $value['value'] ?? $value[$typeId]['value'] ?? '';

            if (is_string($value)) {
                $value = trim($value);
            }

            if (!$value) {
                return null;
            }

            if (isset($config['urlSuffix']) && !str_starts_with($config['urlSuffix'], '#')) {
                $config['urlSuffix'] = StringHelper::ensureLeft($config['urlSuffix'], '?');
            }

            if (isset($linkTypes[$typeId])) {
                $linkType = $linkTypes[$typeId];
            } else {
                $type = self::types()[$typeId] ?? null;
                if (!$type) {
                    throw new InvalidArgumentException("Invalid link type: $typeId");
                }
                $linkType = Component::createComponent($type, BaseLinkType::class);
            }

            if (is_string($value)) {
                $value = str_replace(' ', '+', $value);
            }

            $value = $linkType->normalizeValue($value);
        } else {
            if (!$value) {
                return null;
            }

            $typeId = $this->resolveType($value);
            $linkType = $linkTypes[$typeId] ?? Component::createComponent(self::types()[$typeId], BaseLinkType::class);
            $config = [];
        }

        return new LinkData($value, $linkType, $config);
    }

    /**
     * @inheritdoc
     */
    public function useFieldset(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $linkTypes = $this->getLinkTypes();
        $valueTypeId = null;

        /** @var LinkData|null $value */
        if ($value) {
            $valueTypeId = $value->type;

            if (!isset($linkTypes[$valueTypeId])) {
                $type = self::types()[$valueTypeId] ?? null;
                if ($type) {
                    $linkTypes[$valueTypeId] = Component::createComponent($type, BaseLinkType::class);
                } else {
                    $value = null;
                }
            }
        }

        if (!$value) {
            $valueTypeId = reset($this->types);
        }

        $id = $this->getInputId();
        $view = Craft::$app->getView();

        $view->registerJsWithVars(fn($id) => <<<JS
new Craft.LinkField($('#' + $id));
JS, [
            $view->namespaceInputId($id),
        ]);

        if (!$value) {
            // Override the initial value being set to null by CustomField::inputHtml()
            $view->setInitialDeltaValue($this->handle, [
                'type' => $valueTypeId,
                'value' => '',
            ]);
        }

        $typeInputName = "$this->handle[type]";

        if (count($linkTypes) === 1) {
            $innerHtml = Html::hiddenInput($typeInputName, $valueTypeId);
        } else {
            $namespacedId = $view->namespaceInputId($id);
            $js = <<<JS
$('#$namespacedId-type').on('change', e => {
  const type = $('#$namespacedId-type').val();
  $('#$namespacedId')
    .attr('type', type)
    .attr('inputmode', type);
});
JS;
            $view->registerJs($js);

            $innerHtml = Cp::selectHtml([
                'id' => "$id-type",
                'describedBy' => $this->describedBy,
                'name' => $typeInputName,
                'options' => array_map(fn(BaseLinkType $linkType) => [
                    'label' => $linkType::displayName(),
                    'value' => $linkType::id(),
                ], $linkTypes),
                'value' => $valueTypeId,
                'inputAttributes' => [
                    'aria' => [
                        'label' => Craft::t('app', 'URL type'),
                    ],
                ],
                'toggle' => true,
                'targetPrefix' => "$id-",
            ]);
        }

        foreach ($linkTypes as $typeId => $linkType) {
            $containerId = "$id-$typeId";
            $nsContainerId = $view->namespaceInputId($containerId);
            $selected = $typeId === $valueTypeId;
            $typeValue = $selected ? $value?->serialize()['value'] : null;
            $isTextLink = is_subclass_of($linkType, BaseTextLinkType::class);
            $innerHtml .=
                Html::beginTag('div', [
                    'id' => $containerId,
                    'class' => array_keys(array_filter([
                        'flex-grow' => true,
                        'hidden' => !$selected,
                        'text-link' => $isTextLink,
                    ])),
                    'data' => ['link-type' => $typeId],
                ]) .
                $view->namespaceInputs(
                    fn() => $linkType->inputHtml($this, $typeValue, $nsContainerId),
                    "$this->handle[$typeId]",
                ) .
                Html::endTag('div');
        }

        $pane = $this->showLabelField || !empty($this->advancedFields);
        $html =
            Html::beginTag('div', [
                'id' => $id,
                'class' => $pane ? ['pane', 'hairline', 'padding-m'] : null,
            ]) .
            Html::beginTag('div', [
                'class' => 'link-input',
                'data' => ['link-field' => true],
            ]) .
            Html::tag('div', $innerHtml, [
                'class' => ['flex', 'flex-nowrap'],
            ]) .
            Html::endTag('div');

        if ($this->showLabelField) {
            $html .= Cp::textFieldHtml([
                'fieldClass' => 'my-m',
                'fieldAttributes' => [
                    'data' => ['label-field' => true],
                ],
                'label' => Craft::t('app', 'Label'),
                'id' => "$id-label",
                'name' => "$this->handle[label]",
                'value' => $value?->getLabel(true),
                'placeholder' => $value?->getLabel(false),
            ]);
        }

        if (!empty($this->advancedFields)) {
            $html .=
                Html::a(Craft::t('app', 'Advanced'), options: [
                    'class' => ['fieldtoggle', 'mb-0'],
                    'data' => ['target' => "$id-advanced"],
                ]) .
                Html::beginTag('div', [
                    'id' => "$id-advanced",
                    'class' => ['hidden', 'meta', 'pane', 'hairline'],
                ]);

            foreach ($this->advancedFields as $field) {
                $html .= match ($field) {
                    'urlSuffix' => Cp::textFieldHtml([
                        'fieldClass' => 'info-icon-instructions',
                        'label' => Craft::t('app', 'URL Suffix'),
                        'instructions' => Craft::t('app', 'Query params (e.g. {ex1}) or a URI fragment (e.g. {ex2}) that should be appended to the URL.', [
                            'ex1' => '`?p1=foo&p2=bar`',
                            'ex2' => '`#anchor`',
                        ]),
                        'id' => "$id-url-suffix",
                        'name' => "$this->handle[urlSuffix]",
                        'value' => $value?->urlSuffix,
                    ]),
                    'target' => Cp::lightswitchFieldHtml([
                        'label' => Craft::t('app', 'Open in a new tab'),
                        'id' => "$id-target",
                        'name' => "$this->handle[target]",
                        'on' => $value?->target,
                        'value' => '_blank',
                    ]),
                    'title' => Cp::textFieldHtml([
                        'label' => Craft::t('app', 'Title Text'),
                        'id' => "$id-title",
                        'name' => "$this->handle[title]",
                        'value' => $value?->title,
                    ]),
                    'class' => Cp::textFieldHtml([
                        'fieldClass' => 'info-icon-instructions',
                        'class' => 'code',
                        'label' => Craft::t('app', 'Class Name'),
                        'instructions' => Craft::t('app', 'Separate multiple values with spaces.'),
                        'id' => "$id-class",
                        'name' => "$this->handle[class]",
                        'value' => $value?->class,
                    ]),
                    'id' => Cp::textFieldHtml([
                        'class' => 'code',
                        'label' => Craft::t('app', 'ID'),
                        'id' => "$id-id",
                        'name' => "$this->handle[id]",
                        'value' => $value?->id,
                    ]),
                    'rel' => Cp::textfieldHtml([
                        'fieldClass' => 'info-icon-instructions',
                        'class' => 'code',
                        'label' => Craft::t('app', 'Relation ({ex})', ['ex' => '<code>rel</code>']),
                        'instructions' => Craft::t('app', 'Separate multiple values with spaces.'),
                        'id' => "$id-rel",
                        'name' => "$this->handle[rel]",
                        'value' => $value?->rel,
                    ]),
                    'ariaLabel' => Cp::textFieldHtml([
                        'label' => Craft::t('app', 'ARIA Label'),
                        'id' => "$id-aria-label",
                        'name' => "$this->handle[ariaLabel]",
                        'value' => $value?->ariaLabel,
                    ]),
                };
            }

            $html .= Html::endTag('div');
        }

        $html .= Html::endTag('div');

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [
            [
                function(ElementInterface $element) {
                    /** @var LinkData $value */
                    $value = $element->getFieldValue($this->handle);
                    $linkTypes = $this->getLinkTypes();
                    if (!isset($linkTypes[$value->type])) {
                        $type = self::types()[$value->type] ?? null;
                        $element->addError("field:$this->handle", Craft::t('app', '{attribute} no longer allows {type} links.', [
                            'attribute' => $this->getUiLabel(),
                            'type' => is_subclass_of($type, BaseLinkType::class) ? $type::displayName() : $type,
                        ]));
                        return;
                    }
                    $linkType = $linkTypes[$value->type];
                    $value = $value->serialize()['value'];
                    $error = null;
                    if (!$linkType->validateValue($value, $error)) {
                        /** @var string|null $error */
                        $element->addError("field:$this->handle", $error ?? Craft::t('yii', '{attribute} is invalid.', [
                            'attribute' => $this->getUiLabel(),
                        ]));
                        return;
                    }

                    $stringValidator = new StringValidator(['max' => $this->maxLength]);
                    if (!$stringValidator->validate($value, $error)) {
                        $element->addError("field:$this->handle", $error);
                    }
                },
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getElementConditionRuleType(): array|string|null
    {
        return TextFieldConditionRule::class;
    }

    /**
     * @inheritdoc
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        /** @var LinkData|null $value */
        if (!$value) {
            return '';
        }
        $value = Html::encode((string)$value);
        return "<a href=\"$value\" target=\"_blank\">$value</a>";
    }

    /**
     * @inheritdoc
     */
    public function previewPlaceholderHtml(mixed $value, ?ElementInterface $element): string
    {
        if (!$value) {
            $value = Craft::$app->getSites()->getCurrentSite()->baseUrl;
        }

        return $this->getPreviewHtml($value, new EntryElement());
    }

    /**
     * @inheritdoc
     */
    public function getContentGqlType(): Type|array
    {
        if (!$this->fullGraphqlData) {
            return parent::getContentGqlType();
        }

        return LinkDataType::generateType($this);
    }

    /**
     * @inheritdoc
     */
    public function getContentGqlMutationArgumentType(): Type|array
    {
        if (!$this->fullGraphqlData) {
            return parent::getContentGqlMutationArgumentType();
        }

        $typeName = 'LinkDataInput';
        return GqlEntityRegistry::getOrCreate($typeName, fn() => new InputObjectType([
            'name' => $typeName,
            'fields' => [
                'type' => Type::string(),
                'value' => Type::string(),
                'label' => Type::string(),
                'urlSuffix' => Type::string(),
            ],
        ]));
    }

    /**
     * @inheritdoc
     */
    public function getRelationTargetIds(ElementInterface $element): array
    {
        $targetIds = [];
        /** @var LinkData|null $value */
        $value = $element->getFieldValue($this->handle);
        $element = $value?->getElement();
        if ($element) {
            $targetIds[] = $element->id;
        }
        return $targetIds;
    }
}
