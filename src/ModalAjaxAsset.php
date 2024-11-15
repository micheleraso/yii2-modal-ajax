<?php

namespace micheleraso\widgets\modal;

use yii\web\AssetBundle;

/**
 * Class ModalAjaxAsset
 * @package micheleraso\widgets\modal
 * @author Michele Raso <dev@micheleraso.com>
 */
class ModalAjaxAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public $depends = [
        'yii\web\JqueryAsset',
        'yii\bootstrap5\BootstrapAsset',
    ];

    /**
     * @inheritdoc
     */
    public $js = [
        'js/kb-modal-ajax.js'
    ];

    /**
     * @inheritdoc
     */
    public $css = [
        'css/modal-colors.css',
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . "/assets";
        parent::init();
    }
}
