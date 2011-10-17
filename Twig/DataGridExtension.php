<?php

/*
 * This file is part of the DataGridBundle.
 *
 * (c) Stanislav Turza <sorien@mail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sorien\DataGridBundle\Twig;

/**
 * @todo use {% grid_theme grid '' %} instead of second argument in grid function
 */
class DataGridExtension extends \Twig_Extension
{
    const DEFAULT_TEMPLATE = 'SorienDataGridBundle::blocks.html.twig';

    /**
     * @var \Twig_Environment
     */
    protected $environment;

    /**
     * @var \Twig_Template
     */
    protected $templates;

    /**
     * @var string
     */
    protected $theme;

    /**
    * @var \Symfony\Component\Routing\Router
    */
    protected $router;

    /**
     * @var map grid to grid name in twig
     */
    static $names;

    /**
     * @param \Symfony\Component\Routing\Router $router
     */
    public function __construct($router)
    {
        $this->router = $router;
        $this->templates = array();
    }

    /**
     * {@inheritdoc}
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'grid'              => new \Twig_Function_Method($this, 'getGrid', array('is_safe' => array('html'))),
            'grid_titles'       => new \Twig_Function_Method($this, 'getGridTitles', array('is_safe' => array('html'))),
            'grid_filters'      => new \Twig_Function_Method($this, 'getGridFilters', array('is_safe' => array('html'))),
            'grid_rows'         => new \Twig_Function_Method($this, 'getGridItems', array('is_safe' => array('html'))),
            'grid_pager'        => new \Twig_Function_Method($this, 'getGridPager', array('is_safe' => array('html'))),
            'grid_actions'      => new \Twig_Function_Method($this, 'getGridActions', array('is_safe' => array('html'))),
            'grid_url'          => new \Twig_Function_Method($this, 'getGridUrl'),
            'grid_filter'       => new \Twig_Function_Method($this, 'getGridFilter'),
            'grid_cell'         => new \Twig_Function_Method($this, 'getGridCell', array('is_safe' => array('html'))),
        );
    }

    /**
     * Render grid function
     *
     * @param \Sorien\DataGridBundle\Grid\Grid $grid
     * @param string $theme
     * @param string $id
     * @return string
     */
    public function getGrid($grid, $theme = null, $id = '')
    {
        $this->theme = $theme;

        self::$names[$grid->getHash()] = $id;

        return $this->renderBlock('grid', array('grid' => $grid));
    }

    public function getGridTitles($grid)
    {
        return $this->renderBlock('grid_titles', array('grid' => $grid));
    }

    public function getGridFilters($grid)
    {
        return $this->renderBlock('grid_filters', array('grid' => $grid));
    }

    public function getGridItems($grid)
    {
        return $this->renderBlock('grid_rows', array('grid' => $grid));
    }

    public function getGridPager($grid)
    {
        return $this->renderBlock('grid_pager', array('grid' => $grid));
    }

    public function getGridActions($grid)
    {
        return $this->renderBlock('grid_actions', array('grid' => $grid));
    }

    /**
     * Cell Drawing
     *
     * @param \Sorien\DataGridBundle\Grid\Column\Column $column
     * @param \Sorien\DataGridBundle\Grid\Row $row
     * @param \Sorien\DataGridBundle\Grid\Grid $grid
     *
     * @return string
     */
    public function getGridCell($column, $row, $grid)
    {
        $value = $column->renderCell($row->getField($column->getId()), $row, $this->router);

        if (($id = self::$names[$grid->getHash()]) != '')
        {
            if ($this->hasBlock($block = 'grid_'.$id.'_column_'.$column->getId().'_cell'))
            {
                return $this->renderBlock($block, array('column' => $column, 'value' => $value, 'row' => $row));
            }
        }

        if ($this->hasBlock($block = 'grid_column_'.$column->getId().'_cell'))
        {
            return $this->renderBlock($block, array('column' => $column, 'value' => $value, 'row' => $row));
        }

        return $value;
    }

    /**
     * Filter Drawing
     *
     * @param \Sorien\DataGridBundle\Grid\Column\Column $column
     * @param \Sorien\DataGridBundle\Grid\Grid $grid
     * @todo like a cell drawing
     *
     * @return string
     */
    public function getGridFilter($column, $grid)
    {
        $block = 'grid_column_'.$column->getId().'_filter';

        return $this->hasBlock($block) ?  $this->renderBlock($block, array('column' => $column, 'hash' => $grid->getHash())) : $column->renderFilter($grid->getHash());
    }

    /**
     * Internal function for retrieving grid urls
     *
     * @param $section
     * @param \Sorien\DataGridBundle\Grid\Grid $grid
     * @param null $param
     * @return string
     */
    public function getGridUrl($section, $grid, $param = null)
    {
        if ($section == 'order')
        {
            if ($param->isSorted())
            {
                return $grid->getRouteUrl().'?'.$grid->getHash().'[_order]='.$param->getId().'|'.$this->nextOrder($param->getOrder());
            }
            else
            {
                return $grid->getRouteUrl().'?'.$grid->getHash().'[_order]='.$param->getId().'|asc';
            }
        }
        elseif ($section == 'page')
        {
            return $grid->getRouteUrl().'?'.$grid->getHash().'[_page]='.$param;
        }
        elseif ($section == 'limit')
        {
            return $grid->getRouteUrl().'?'.$grid->getHash().'[_limit]=';
        }
    }

    public function nextOrder($value)
    {
        return  $value == 'asc' ? 'desc' : 'asc';
    }

    /**
     * Render block 
     *
     * @param $name string
     * @param $parameters string
     * @return string
     */
    private function renderBlock($name, $parameters)
    {
        foreach ($this->getTemplates() as $template)
        {
            if ($template->hasBlock($name))
            {
                return $template->renderBlock($name, $parameters);
            }
        }

        throw new \InvalidArgumentException(sprintf('Block "%s" doesn\'t exist in grid template "%s".', $name, $this->theme));
    }

    /**
     * Has block
     *
     * @param $name string
     * @return boolean
     */
    private function hasBlock($name)
    {
        foreach ($this->getTemplates() as $template)
        {
            if ($template->hasBlock($name))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    private function getTemplates()
    {
        if (empty($this->templates))
        {
            //get template name
            if ($this->theme instanceof \Twig_Template)
            {
                $this->templates[] = $this->theme;
                $this->templates[] = $this->environment->loadTemplate($this::DEFAULT_TEMPLATE);
            }
            elseif (is_string($this->theme))
            {
                $template = $this->environment->loadTemplate($this->theme);
                while ($template != null)
                {
                    $this->templates[] = $template;
                    $template = $template->getParent(array());
                }

                $this->templates[] = $this->environment->loadTemplate($this->theme);
            }
            elseif (is_null($this->theme))
            {
                $this->templates[] = $this->environment->loadTemplate($this::DEFAULT_TEMPLATE);
            }
            else
            {
                throw new \Exception('Bad template definition for grid');
            }
        }

        return $this->templates;
    }

    public function getName()
    {
        return 'datagrid_twig_extension';
    }
}