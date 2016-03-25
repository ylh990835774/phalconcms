<?php
/**
 * @author Uhon Liu http://phalconcmf.com <futustar@qq.com>
 */

namespace Core\Models;

use Phalcon\Mvc\Model;

class UserRules extends Model
{
    /**
     *
     * @var integer
     */
    public $rule_id;

    /**
     *
     * @var string
     */
    public $module;

    /**
     *
     * @var string
     */
    public $module_name;

    /**
     *
     * @var string
     */
    public $controller;

    /**
     *
     * @var string
     */
    public $controller_name;

    /**
     *
     * @var string
     */
    public $action;

    /**
     *
     * @var string
     */
    public $action_name;

    /**
     *
     * @var string
     */
    public $sub_action;

    /**
     *
     * @var string
     */
    public $mca;

    /**
     * Initialize method for model
     */
    public function initialize()
    {

    }
}