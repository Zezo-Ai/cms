<?php

namespace craft\elements\conditions;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\fields\BaseRelationField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;

/**
 * Relation condition rule.
 *
 * @property int[] $elementIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class RelatedToConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @var string
     * @phpstan-var class-string<ElementInterface>
     */
    public string $elementType = Entry::class;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('app', 'Related To');
    }

    /**
     * @inheritdoc
     */
    protected function elementType(): string
    {
        return $this->elementType;
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inerhitdoc
     */
    protected function elementSelectHtmlConfig(): array
    {
        return array_merge(parent::elementSelectHtmlConfig(), [
            'showSiteMenu' => true,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $elementId = $this->getElementId();
        if ($elementId !== null) {
            $query->andRelatedTo($elementId);
        }
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        $id = 'element-type';
        return Html::hiddenLabel($this->getLabel(), $id) .
            Html::tag('div',
                Cp::selectHtml([
                    'id' => $id,
                    'name' => 'elementType',
                    'options' => $this->_elementTypeOptions(),
                    'value' => $this->elementType,
                    'inputAttributes' => [
                        'hx' => [
                            'post' => UrlHelper::actionUrl('conditions/render'),
                        ],
                    ],
                ]) .
                parent::inputHtml(),
                [
                    'class' => ['flex', 'flex-start'],
                ]
            );
    }

    /**
     * @return array
     */
    private function _elementTypeOptions(): array
    {
        $options = [];
        foreach (Craft::$app->getFields()->getRelationalFieldTypes() as $field) {
            /** @var string|BaseRelationField $field */
            /** @var string|ElementInterface $elementType */
            $elementType = $field::elementType();
            $options[] = [
                'value' => $elementType,
                'label' => $elementType::displayName(),
            ];
        }
        return $options;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['elementType'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'elementType' => $this->elementType,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $elementId = $this->getElementId();
        if (!$elementId) {
            return true;
        }

        return $element::find()
            ->id($element->id ?: false)
            ->site('*')
            ->drafts($element->getIsDraft())
            ->provisionalDrafts($element->isProvisionalDraft)
            ->revisions($element->getIsRevision())
            ->status(null)
            ->relatedTo($elementId)
            ->exists();
    }
}
