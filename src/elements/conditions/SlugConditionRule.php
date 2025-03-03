<?php

namespace craft\elements\conditions;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;

/**
 * Slug condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class SlugConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('app', 'Slug');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['slug'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->slug($this->paramValue());
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->slug);
    }

    /**
     * @inheritdoc
     */
    protected function matchValue(mixed $value): bool
    {
        return match ($this->operator) {
            self::OPERATOR_EMPTY => !$value || ElementHelper::isTempSlug($value),
            self::OPERATOR_NOT_EMPTY => (bool)$value && !ElementHelper::isTempSlug($value),
            default => parent::matchValue($value),
        };
    }
}
