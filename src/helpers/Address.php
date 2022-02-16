<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\helpers;

use Craft;
use craft\web\assets\addresses\AddressesAsset;

/**
 * Class Address
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class Address
{

    /**
     * @param array $addresses
     * @param string $namespace
     * @param bool $openNew
     */
    public static function addressCardsHtml(array $addresses, string $namespace = 'addresses', bool $openNew = false): string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(AddressesAsset::class);
        $namespacedId = $view->namespaceInputId($namespace);

        return $view->namespaceInputs(function() use ($view, $namespacedId, $addresses, $openNew) {

            $isHtmxRequest = Craft::$app->getRequest()->getHeaders()->has('HX-Request');
            $view->startJsBuffer();

            $html = Html::beginTag('div', [
                'id' => 'container',
                'hx' => [
                    'ext' => 'craft-cp',
                    'target' => "#$namespacedId-container", // replace self
                    'include' => "#$namespacedId-container", // Only the current address data
                    'indicator' => sprintf('#%s', $view->namespaceInputId('address-card')),
                ]
            ]);
            $html .= Html::beginTag('div', ['class' => 'address-cards']);

            $addressCount = 0;
            foreach ($addresses as $address) {
                $addressCount++;
                $namespace = $address->id ? (string)$address->id : ('new' . $addressCount);
                $namespacedName = $view->namespaceInputName($namespace);
                $autoOpen = ($openNew && StringHelper::startsWith($namespace,'new', false) && $addressCount == count($addresses));
                $html .= $view->namespaceInputs(function() use ($address, $namespacedName, $autoOpen) {
                    return Craft::$app->getView()->renderTemplate('_includes/forms/address', [
                        'id' => 'address',
                        'address' => $address,
                        'name' => $namespacedName,
                        'availableCountries' => null,
                        'defaultCountryCode' => 'US',
                        'autoOpen' => $autoOpen,
                        'hasErrors' => $address->hasErrors()
                    ]);
                }, $namespace);
            }

            $html .= Html::tag('button', Craft::t('app', 'Add an address'),
                [
                    'class' => 'address-card add icon',
                    'hx' => [
                        'get' => UrlHelper::actionUrl('addresses/add-address'),
                    ]
                ]);

            $html .= Html::endTag('div');

            $rulesJs = $view->clearJsBuffer(false);

            if ($rulesJs) {
                if ($isHtmxRequest) {
                    $html .= html::tag('script', $rulesJs, ['type' => 'text/javascript']);
                } else {
                    $view->registerJs($rulesJs);
                }
            }

            // Add head and foot/body scripts to html returned so crafts htmx condition builder can insert them into the DOM
            // If this is not an htmx request, don't add scripts, since they will be in the page anyway.
            if ($isHtmxRequest) {
                if ($bodyHtml = $view->getBodyHtml()) {
                    $html .= html::tag('template', $bodyHtml, [
                        'id' => 'body-html',
                        'class' => ['hx-body-html'],
                    ]);
                }
                if ($headHtml = $view->getHeadHtml()) {
                    $html .= html::tag('template', $headHtml, [
                        'id' => 'head-html',
                        'class' => ['hx-head-html'],
                    ]);
                }
            } else {
                $view->registerJsWithVars(
                    fn($containerSelector) => <<<JS
htmx.process(htmx.find($containerSelector));
htmx.trigger(htmx.find($containerSelector), 'htmx:load');
JS,
                    [sprintf('#%s', $namespacedId)]
                );
            }
            $html .= Html::endTag('div');
            return $html;
        }, $namespace);
    }
}
