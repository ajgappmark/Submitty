<?php

namespace app\views\grading;

use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;


class NewView extends AbstractView {
    /**
     * @param User[] $students
     * @return string
     */
    public function showNewView() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return $this->core->getOutput()->renderTwigTemplate("grading/electronic/newgradingview.twig", [

        ]);
    }
}
