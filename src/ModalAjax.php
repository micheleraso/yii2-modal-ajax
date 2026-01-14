<?php

namespace micheleraso\widgets\modal;

use yii\bootstrap5\Modal;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;

/**
 * Class ModalAjax
 *
 * @package micheleraso\widgets\modal
 * @author  Michele Raso <dev@micheleraso.com>
 */
class ModalAjax extends Modal
{
    const MODE_SINGLE = 'id';
    const MODE_MULTI = 'multi';

    /**
     * events
     */
    const EVENT_BEFORE_SHOW = 'kbModalBeforeShow';
    const EVENT_MODAL_SHOW = 'kbModalShow';
    const EVENT_BEFORE_SUBMIT = 'kbModalBeforeSubmit';
    const EVENT_MODAL_SUBMIT = 'kbModalSubmit';
    const EVENT_MODAL_SUBMIT_COMPLETE = 'kbModalSubmitComplete';
    const EVENT_MODAL_SHOW_COMPLETE = 'kbModalShowComplete';

    /**
     * @var array
     */
    public $events = [];

    /**
     * The selector to get url request when modal is opened for multy mode
     *
     * @var string
     */
    public $selector;

    /**
     * The url to request when modal is opened for single mode
     *
     * @var string
     */
    public $url;

    /**
     * reload pjax container after ajaxSubmit
     *
     * @var string
     */
    public $pjaxContainer;

    /**
     * timeout in miliseconds for pjax call
     *
     * @var string
     */
    public $pjaxTimeout = 1000;

    /**
     * Submit the form via ajax
     *
     * @var boolean
     */
    public $ajaxSubmit = true;

    /**
     * Submit the form via ajax
     *
     * @var boolean
     */
    public $autoClose = false;

    /**
     * @var string
     */
    protected $mode = self::MODE_SINGLE;

    /**
     * Renders the header HTML markup of the modal
     *
     * @return string the rendering result
     */
    protected function renderHeader(): string
    {
        $button = $this->renderCloseButton();
        if (isset($this->title)) {
            Html::addCssClass($this->titleOptions, ['widget' => 'modal-title']);
            $header = Html::tag('h5', $this->title, $this->titleOptions);
        } else {
            $header = '';
        }

        if ($button !== null) {
            $header .= "\n" . $button;
        } elseif ($header === '') {
            return '';
        }
        Html::addCssClass($this->headerOptions, ['widget' => 'modal-header']);

        return Html::tag('div', "\n" . $header . "\n", $this->headerOptions);
    }

    /**
     * @inheritdocs
     */
    public function init(): void
    {
        parent::init();

        if ($this->selector) {
            $this->mode = self::MODE_MULTI;
        }
    }

    /**
     * @inheritdocs
     */
    public function run(): string
    {
        $result = parent::run();

        /** @var View */
        $view = $this->getView();
        $id = $this->options['id'];

        ModalAjaxAsset::register($view);

        if (!$this->url && !$this->selector) {
            return $result;
        }

        switch ($this->mode) {
            case self::MODE_SINGLE:
                $this->registerSingleModal($id, $view);
                break;

            case self::MODE_MULTI:
                $this->registerMultyModal($id, $view);
                break;
        }

        if (!isset($this->events[self::EVENT_MODAL_SUBMIT])) {
            $this->defaultSubmitEvent();
        }

        $this->registerEvents($id, $view);

        return $result;
    }

    /**
     * @param      $id
     * @param View $view
     */
    protected function registerSingleModal($id, $view)
    {
        $url = is_array($this->url) ? Url::to($this->url) : $this->url;
        $ajaxSubmit = $this->ajaxSubmit ? 'true' : 'false';

        $view->registerJs("
            jQuery(document).ready(function() {
                if (typeof jQuery('#$id').kbModalAjax === 'function') {
                    jQuery('#$id').kbModalAjax({
                        url: '$url',
                        size: 'sm',
                        ajaxSubmit: $ajaxSubmit
                    });
                }
            });
        ", View::POS_READY);
    }

    /**
     * @param      $id
     * @param View $view
     */
    /**
     * @param      $id
     * @param View $view
     */
    protected function registerMultyModal($id, $view)
    {
        $ajaxSubmit = $this->ajaxSubmit ? 'true' : 'false';

        $view->registerJs("
            jQuery(document).off('click', '$this->selector').on('click', '$this->selector', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var clickedElement = jQuery(this);
                var bs_url = clickedElement.attr('href');
                var title = clickedElement.attr('title');
                
                if (!title) title = ' ';
                
                var modalElement = jQuery('#$id');
                
                if (modalElement.length > 0) {
                    // Aggiorna il titolo
                    modalElement.find('.modal-header h5, .modal-header .modal-title').html(title);
                    
                    // Inizializza il plugin se esiste
                    if (typeof modalElement.kbModalAjax === 'function') {
                        modalElement.kbModalAjax({
                            selector: clickedElement,
                            url: bs_url,
                            ajaxSubmit: $ajaxSubmit
                        });
                    }
                    
                    // Mostra il modal con Bootstrap 5
                    var modal = bootstrap.Modal.getOrCreateInstance(modalElement[0]);
                    modal.show();
                }
                
                return false;
            });
        ", View::POS_READY);
    }

    /**
     * register pjax event
     */
    protected function defaultSubmitEvent()
    {
        $expression = [];

        if ($this->autoClose) {
            // Bootstrap 5: usa bootstrap.Modal invece di modal('toggle')
            $expression[] = "var modalEl = document.getElementById('{$this->options['id']}'); var modal = bootstrap.Modal.getInstance(modalEl); if(modal) modal.hide();";
        }

        if ($this->pjaxContainer) {
            $expression[] = "if (typeof $.pjax !== 'undefined') { $.pjax.reload({container : '$this->pjaxContainer', timeout : $this->pjaxTimeout }); }";
        }

        $script = implode("\r\n                        ", $expression);

        if (!empty($script)) {
            $this->events[self::EVENT_MODAL_SUBMIT] = new JsExpression("
                function(event, data, status, xhr) {
                    if(status){
                        $script
                    }
                }
            ");
        }
    }

    /**
     * @param      $id
     * @param View $view
     */
    protected function registerEvents($id, $view)
    {
        if (empty($this->events)) {
            return;
        }

        $js = [];
        foreach ($this->events as $event => $expression) {
            // Converti l'espressione in stringa se necessario
            $expressionStr = $expression instanceof JsExpression ? $expression->expression : $expression;
            $js[] = ".on('$event', $expressionStr)";
        }

        if (!empty($js)) {
            $script = "jQuery('#$id')" . implode("", $js) . ";";
            $view->registerJs($script, View::POS_READY);
        }
    }
}
