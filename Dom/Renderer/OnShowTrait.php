<?php


namespace Dom\Renderer;


trait OnShowTrait
{

    /**
     * @var null|callable
     */
    private $_onShow = null;

    /**
     * @return callable|null
     */
    public function getOnShow(): ?callable
    {
        return $this->_onShow;
    }

    /**
     * eg setOnShow(function (\Dom\Template $template, $ren, ...) {  });
     *
     * @param callable|null $onShow
     * @return $this
     */
    public function setOnShow(?callable $onShow)
    {
        $this->_onShow = $onShow;
        return $this;
    }

}