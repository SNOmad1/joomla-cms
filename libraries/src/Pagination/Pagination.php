<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Pagination;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Pagination Class. Provides a common interface for content pagination for the Joomla! CMS.
 *
 * @since  1.5
 */
class Pagination
{
	/**
	 * @var    integer  The record number to start displaying from.
	 * @since  1.5
	 */
	public $limitstart = null;

	/**
	 * @var    integer  Number of rows to display per page.
	 * @since  1.5
	 */
	public $limit = null;

	/**
	 * @var    integer  Total number of rows.
	 * @since  1.5
	 */
	public $total = null;

	/**
	 * @var    integer  Prefix used for request variables.
	 * @since  1.6
	 */
	public $prefix = null;

	/**
	 * @var    integer  Value pagination object begins at
	 * @since  3.0
	 */
	public $pagesStart;

	/**
	 * @var    integer  Value pagination object ends at
	 * @since  3.0
	 */
	public $pagesStop;

	/**
	 * @var    integer  Current page
	 * @since  3.0
	 */
	public $pagesCurrent;

	/**
	 * @var    integer  Total number of pages
	 * @since  3.0
	 */
	public $pagesTotal;

	/**
	 * @var    boolean  View all flag
	 * @since  3.0
	 */
	protected $viewall = false;

	/**
	 * Additional URL parameters to be added to the pagination URLs generated by the class.  These
	 * may be useful for filters and extra values when dealing with lists and GET requests.
	 *
	 * @var    array
	 * @since  3.0
	 */
	protected $additionalUrlParams = array();

	/**
	 * @var    CMSApplication  The application object
	 * @since  3.4
	 */
	protected $app = null;

	/**
	 * Pagination data object
	 *
	 * @var    object
	 * @since  3.4
	 */
	protected $data;

	/**
	 * Constructor.
	 *
	 * @param   integer         $total       The total number of items.
	 * @param   integer         $limitstart  The offset of the item to start at.
	 * @param   integer         $limit       The number of items to display per page.
	 * @param   string          $prefix      The prefix used for request variables.
	 * @param   CMSApplication  $app         The application object
	 *
	 * @since   1.5
	 */
	public function __construct($total, $limitstart, $limit, $prefix = '', CMSApplication $app = null)
	{
		// Value/type checking.
		$this->total = (int) $total;
		$this->limitstart = (int) max($limitstart, 0);
		$this->limit = (int) max($limit, 0);
		$this->prefix = $prefix;
		$this->app = $app ?: Factory::getApplication();

		if ($this->limit > $this->total)
		{
			$this->limitstart = 0;
		}

		if (!$this->limit)
		{
			$this->limit = $total;
			$this->limitstart = 0;
		}

		/*
		 * If limitstart is greater than total (i.e. we are asked to display records that don't exist)
		 * then set limitstart to display the last natural page of results
		 */
		if ($this->limitstart > $this->total - $this->limit)
		{
			$this->limitstart = max(0, (int) (ceil($this->total / $this->limit) - 1) * $this->limit);
		}

		// Set the total pages and current page values.
		if ($this->limit > 0)
		{
			$this->pagesTotal = ceil($this->total / $this->limit);
			$this->pagesCurrent = ceil(($this->limitstart + 1) / $this->limit);
		}

		// Set the pagination iteration loop values.
		$displayedPages = 10;
		$this->pagesStart = $this->pagesCurrent - ($displayedPages / 2);

		if ($this->pagesStart < 1)
		{
			$this->pagesStart = 1;
		}

		if ($this->pagesStart + $displayedPages > $this->pagesTotal)
		{
			$this->pagesStop = $this->pagesTotal;

			if ($this->pagesTotal < $displayedPages)
			{
				$this->pagesStart = 1;
			}
			else
			{
				$this->pagesStart = $this->pagesTotal - $displayedPages + 1;
			}
		}
		else
		{
			$this->pagesStop = $this->pagesStart + $displayedPages - 1;
		}

		// If we are viewing all records set the view all flag to true.
		if ($limit === 0)
		{
			$this->viewall = true;
		}
	}

	/**
	 * Method to set an additional URL parameter to be added to all pagination class generated
	 * links.
	 *
	 * @param   string  $key    The name of the URL parameter for which to set a value.
	 * @param   mixed   $value  The value to set for the URL parameter.
	 *
	 * @return  mixed  The old value for the parameter.
	 *
	 * @since   1.6
	 */
	public function setAdditionalUrlParam($key, $value)
	{
		// Get the old value to return and set the new one for the URL parameter.
		$result = $this->additionalUrlParams[$key] ?? null;

		// If the passed parameter value is null unset the parameter, otherwise set it to the given value.
		if ($value === null)
		{
			unset($this->additionalUrlParams[$key]);
		}
		else
		{
			$this->additionalUrlParams[$key] = $value;
		}

		return $result;
	}

	/**
	 * Method to get an additional URL parameter (if it exists) to be added to
	 * all pagination class generated links.
	 *
	 * @param   string  $key  The name of the URL parameter for which to get the value.
	 *
	 * @return  mixed  The value if it exists or null if it does not.
	 *
	 * @since   1.6
	 */
	public function getAdditionalUrlParam($key)
	{
		return $this->additionalUrlParams[$key] ?? null;
	}

	/**
	 * Return the rationalised offset for a row with a given index.
	 *
	 * @param   integer  $index  The row index
	 *
	 * @return  integer  Rationalised offset for a row with a given index.
	 *
	 * @since   1.5
	 */
	public function getRowOffset($index)
	{
		return $index + 1 + $this->limitstart;
	}

	/**
	 * Return the pagination data object, only creating it if it doesn't already exist.
	 *
	 * @return  \stdClass  Pagination data object.
	 *
	 * @since   1.5
	 */
	public function getData()
	{
		if (!$this->data)
		{
			$this->data = $this->_buildDataObject();
		}

		return $this->data;
	}

	/**
	 * Create and return the pagination pages counter string, ie. Page 2 of 4.
	 *
	 * @return  string   Pagination pages counter string.
	 *
	 * @since   1.5
	 */
	public function getPagesCounter()
	{
		$html = null;

		if ($this->pagesTotal > 1)
		{
			$html .= Text::sprintf('JLIB_HTML_PAGE_CURRENT_OF_TOTAL', $this->pagesCurrent, $this->pagesTotal);
		}

		return $html;
	}

	/**
	 * Create and return the pagination result set counter string, e.g. Results 1-10 of 42
	 *
	 * @return  string   Pagination result set counter string.
	 *
	 * @since   1.5
	 */
	public function getResultsCounter()
	{
		$html = null;
		$fromResult = $this->limitstart + 1;

		// If the limit is reached before the end of the list.
		if ($this->limitstart + $this->limit < $this->total)
		{
			$toResult = $this->limitstart + $this->limit;
		}
		else
		{
			$toResult = $this->total;
		}

		// If there are results found.
		if ($this->total > 0)
		{
			$msg = Text::sprintf('JLIB_HTML_RESULTS_OF', $fromResult, $toResult, $this->total);
			$html .= "\n" . $msg;
		}
		else
		{
			$html .= "\n" . Text::_('JLIB_HTML_NO_RECORDS_FOUND');
		}

		return $html;
	}

	/**
	 * Create and return the pagination page list string, ie. Previous, Next, 1 2 3 ... x.
	 *
	 * @return  string  Pagination page list string.
	 *
	 * @since   1.5
	 */
	public function getPagesLinks()
	{
		// Build the page navigation list.
		$data = $this->_buildDataObject();

		$list           = array();
		$list['prefix'] = $this->prefix;

		$itemOverride = false;
		$listOverride = false;

		$chromePath = JPATH_THEMES . '/' . $this->app->getTemplate() . '/html/pagination.php';

		if (file_exists($chromePath))
		{
			include_once $chromePath;

			/*
			 * @deprecated 4.0 Item rendering should use a layout
			 */
			if (function_exists('pagination_item_active') && function_exists('pagination_item_inactive'))
			{
				\JLog::add(
					'pagination_item_active and pagination_item_inactive are deprecated. Use the layout joomla.pagination.link instead.',
					\JLog::WARNING,
					'deprecated'
				);

				$itemOverride = true;
			}

			/*
			 * @deprecated 4.0 The list rendering is now a layout.
			 * @see Pagination::_list_render()
			 */
			if (function_exists('pagination_list_render'))
			{
				\JLog::add('pagination_list_render is deprecated. Use the layout joomla.pagination.list instead.', \JLog::WARNING, 'deprecated');
				$listOverride = true;
			}
		}

		// Build the select list
		if ($data->all->base !== null)
		{
			$list['all']['active'] = true;
			$list['all']['data']   = $itemOverride ? pagination_item_active($data->all) : $this->_item_active($data->all);
		}
		else
		{
			$list['all']['active'] = false;
			$list['all']['data']   = $itemOverride ? pagination_item_inactive($data->all) : $this->_item_inactive($data->all);
		}

		if ($data->start->base !== null)
		{
			$list['start']['active'] = true;
			$list['start']['data']   = $itemOverride ? pagination_item_active($data->start) : $this->_item_active($data->start);
		}
		else
		{
			$list['start']['active'] = false;
			$list['start']['data']   = $itemOverride ? pagination_item_inactive($data->start) : $this->_item_inactive($data->start);
		}

		if ($data->previous->base !== null)
		{
			$list['previous']['active'] = true;
			$list['previous']['data']   = $itemOverride ? pagination_item_active($data->previous) : $this->_item_active($data->previous);
		}
		else
		{
			$list['previous']['active'] = false;
			$list['previous']['data']   = $itemOverride ? pagination_item_inactive($data->previous) : $this->_item_inactive($data->previous);
		}

		// Make sure it exists
		$list['pages'] = array();

		foreach ($data->pages as $i => $page)
		{
			if ($page->base !== null)
			{
				$list['pages'][$i]['active'] = true;
				$list['pages'][$i]['data']   = $itemOverride ? pagination_item_active($page) : $this->_item_active($page);
			}
			else
			{
				$list['pages'][$i]['active'] = false;
				$list['pages'][$i]['data']   = $itemOverride ? pagination_item_inactive($page) : $this->_item_inactive($page);
			}
		}

		if ($data->next->base !== null)
		{
			$list['next']['active'] = true;
			$list['next']['data']   = $itemOverride ? pagination_item_active($data->next) : $this->_item_active($data->next);
		}
		else
		{
			$list['next']['active'] = false;
			$list['next']['data']   = $itemOverride ? pagination_item_inactive($data->next) : $this->_item_inactive($data->next);
		}

		if ($data->end->base !== null)
		{
			$list['end']['active'] = true;
			$list['end']['data']   = $itemOverride ? pagination_item_active($data->end) : $this->_item_active($data->end);
		}
		else
		{
			$list['end']['active'] = false;
			$list['end']['data']   = $itemOverride ? pagination_item_inactive($data->end) : $this->_item_inactive($data->end);
		}

		if ($this->total > $this->limit)
		{
			return $listOverride ? pagination_list_render($list) : $this->_list_render($list);
		}
		else
		{
			return '';
		}
	}

	/**
	 * Get the pagination links
	 *
	 * @param   string  $layoutId  Layout to render the links
	 * @param   array   $options   Optional array with settings for the layout
	 *
	 * @return  string  Pagination links.
	 *
	 * @since   3.3
	 */
	public function getPaginationLinks($layoutId = 'joomla.pagination.links', $options = array())
	{
		// Allow to receive a null layout
		$layoutId = $layoutId ?? 'joomla.pagination.links';

		$list = array(
			'prefix'       => $this->prefix,
			'limit'        => $this->limit,
			'limitstart'   => $this->limitstart,
			'total'        => $this->total,
			'limitfield'   => $this->getLimitBox(),
			'pagescounter' => $this->getPagesCounter(),
			'pages'        => $this->getPaginationPages(),
			'pagesTotal'   => $this->pagesTotal,
		);

		return \JLayoutHelper::render($layoutId, array('list' => $list, 'options' => $options));
	}

	/**
	 * Create and return the pagination pages list, ie. Previous, Next, 1 2 3 ... x.
	 *
	 * @return  array  Pagination pages list.
	 *
	 * @since   3.3
	 */
	public function getPaginationPages()
	{
		$list = array();

		if ($this->total > $this->limit)
		{
			// Build the page navigation list.
			$data = $this->_buildDataObject();

			// All
			$list['all']['active'] = $data->all->base !== null;
			$list['all']['data']   = $data->all;

			// Start
			$list['start']['active'] = $data->start->base !== null;
			$list['start']['data']   = $data->start;

			// Previous link
			$list['previous']['active'] = $data->previous->base !== null;
			$list['previous']['data']   = $data->previous;

			// Make sure it exists
			$list['pages'] = array();

			foreach ($data->pages as $i => $page)
			{
				$list['pages'][$i]['active'] = $page->base !== null;
				$list['pages'][$i]['data']   = $page;
			}

			$list['next']['active'] = $data->next->base !== null;
			$list['next']['data']   = $data->next;

			$list['end']['active'] = $data->end->base !== null;
			$list['end']['data']   = $data->end;
		}

		return $list;
	}

	/**
	 * Return the pagination footer.
	 *
	 * @return  string  Pagination footer.
	 *
	 * @since   1.5
	 */
	public function getListFooter()
	{
		// Keep B/C for overrides done with chromes
		$chromePath = JPATH_THEMES . '/' . $this->app->getTemplate() . '/html/pagination.php';

		if (file_exists($chromePath))
		{
			include_once $chromePath;

			if (function_exists('pagination_list_footer'))
			{
				\JLog::add('pagination_list_footer is deprecated. Use the layout joomla.pagination.links instead.', \JLog::WARNING, 'deprecated');

				$list = array(
					'prefix'       => $this->prefix,
					'limit'        => $this->limit,
					'limitstart'   => $this->limitstart,
					'total'        => $this->total,
					'limitfield'   => $this->getLimitBox(),
					'pagescounter' => $this->getPagesCounter(),
					'pageslinks'   => $this->getPagesLinks(),
				);

				return pagination_list_footer($list);
			}
		}

		return $this->getPaginationLinks();
	}

	/**
	 * Creates a dropdown box for selecting how many records to show per page.
	 *
	 * @return  string  The HTML for the limit # input box.
	 *
	 * @since   1.5
	 */
	public function getLimitBox()
	{
		$limits = array();

		// Make the option list.
		for ($i = 5; $i <= 30; $i += 5)
		{
			$limits[] = HTMLHelper::_('select.option', "$i");
		}

		$limits[] = HTMLHelper::_('select.option', '50', Text::_('J50'));
		$limits[] = HTMLHelper::_('select.option', '100', Text::_('J100'));
		$limits[] = HTMLHelper::_('select.option', '0', Text::_('JALL'));

		$selected = $this->viewall ? 0 : $this->limit;

		// Build the select list.
		if ($this->app->isClient('administrator'))
		{
			$html = HTMLHelper::_(
				'select.genericlist',
				$limits,
				$this->prefix . 'limit',
				'class="form-control" onchange="Joomla.submitform();"',
				'value',
				'text',
				$selected
			);
		}
		else
		{
			$html = HTMLHelper::_(
				'select.genericlist',
				$limits,
				$this->prefix . 'limit',
				'class="form-control" onchange="this.form.submit()"',
				'value',
				'text',
				$selected
			);
		}

		return $html;
	}

	/**
	 * Return the icon to move an item UP.
	 *
	 * @param   integer  $i          The row index.
	 * @param   boolean  $condition  True to show the icon.
	 * @param   string   $task       The task to fire.
	 * @param   string   $alt        The image alternative text string.
	 * @param   boolean  $enabled    An optional setting for access control on the action.
	 * @param   string   $checkbox   An optional prefix for checkboxes.
	 *
	 * @return  string   Either the icon to move an item up or a space.
	 *
	 * @since   1.5
	 */
	public function orderUpIcon($i, $condition = true, $task = 'orderup', $alt = 'JLIB_HTML_MOVE_UP', $enabled = true, $checkbox = 'cb')
	{
		if (($i > 0 || ($i + $this->limitstart > 0)) && $condition)
		{
			return HTMLHelper::_('jgrid.orderUp', $i, $task, '', $alt, $enabled, $checkbox);
		}
		else
		{
			return '&#160;';
		}
	}

	/**
	 * Return the icon to move an item DOWN.
	 *
	 * @param   integer  $i          The row index.
	 * @param   integer  $n          The number of items in the list.
	 * @param   boolean  $condition  True to show the icon.
	 * @param   string   $task       The task to fire.
	 * @param   string   $alt        The image alternative text string.
	 * @param   boolean  $enabled    An optional setting for access control on the action.
	 * @param   string   $checkbox   An optional prefix for checkboxes.
	 *
	 * @return  string   Either the icon to move an item down or a space.
	 *
	 * @since   1.5
	 */
	public function orderDownIcon($i, $n, $condition = true, $task = 'orderdown', $alt = 'JLIB_HTML_MOVE_DOWN', $enabled = true, $checkbox = 'cb')
	{
		if (($i < $n - 1 || $i + $this->limitstart < $this->total - 1) && $condition)
		{
			return HTMLHelper::_('jgrid.orderDown', $i, $task, '', $alt, $enabled, $checkbox);
		}
		else
		{
			return '&#160;';
		}
	}

	/**
	 * Create the HTML for a list footer
	 *
	 * @param   array  $list  Pagination list data structure.
	 *
	 * @return  string  HTML for a list footer
	 *
	 * @since   1.5
	 */
	protected function _list_footer($list)
	{
		$html = "<div class=\"list-footer\">\n";

		$html .= "\n<div class=\"limit\">" . Text::_('JGLOBAL_DISPLAY_NUM') . $list['limitfield'] . "</div>";
		$html .= $list['pageslinks'];
		$html .= "\n<div class=\"counter\">" . $list['pagescounter'] . "</div>";

		$html .= "\n<input type=\"hidden\" name=\"" . $list['prefix'] . "limitstart\" value=\"" . $list['limitstart'] . "\">";
		$html .= "\n</div>";

		return $html;
	}

	/**
	 * Create the html for a list footer
	 *
	 * @param   array  $list  Pagination list data structure.
	 *
	 * @return  string  HTML for a list start, previous, next,end
	 *
	 * @since   1.5
	 */
	protected function _list_render($list)
	{
		return \JLayoutHelper::render('joomla.pagination.list', array('list' => $list));
	}

	/**
	 * Method to create an active pagination link to the item
	 *
	 * @param   PaginationObject  $item  The object with which to make an active link.
	 *
	 * @return  string  HTML link
	 *
	 * @since   1.5
	 * @note    As of 4.0 this method will proxy to `\JLayoutHelper::render('joomla.pagination.link', ['data' => $item, 'active' => true])`
	 */
	protected function _item_active(PaginationObject $item)
	{
		$title = '';
		$class = '';

		if (!is_numeric($item->text))
		{
			$title = ' title="' . $item->text . '"';
			$class = 'hasTooltip ';
		}

		if ($this->app->isClient('administrator'))
		{
			return '<a' . $title . ' href="#" onclick="document.adminForm.' . $this->prefix
			. 'limitstart.value=' . ($item->base > 0 ? $item->base : '0') . '; Joomla.submitform();return false;">' . $item->text . '</a>';
		}
		else
		{
			return '<a' . $title . ' href="' . $item->link . '" class="' . $class . 'page-link">' . $item->text . '</a>';
		}
	}

	/**
	 * Method to create an inactive pagination string
	 *
	 * @param   PaginationObject  $item  The item to be processed
	 *
	 * @return  string
	 *
	 * @since   1.5
	 * @note    As of 4.0 this method will proxy to `\JLayoutHelper::render('joomla.pagination.link', ['data' => $item, 'active' => false])`
	 */
	protected function _item_inactive(PaginationObject $item)
	{
		if ($this->app->isClient('administrator'))
		{
			return '<span>' . $item->text . '</span>';
		}
		else
		{
			return '<span class="page-link">' . $item->text . '</span>';
		}
	}

	/**
	 * Create and return the pagination data object.
	 *
	 * @return  \stdClass  Pagination data object.
	 *
	 * @since   1.5
	 */
	protected function _buildDataObject()
	{
		$data = new \stdClass;

		// Build the additional URL parameters string.
		$params = '';

		if (!empty($this->additionalUrlParams))
		{
			foreach ($this->additionalUrlParams as $key => $value)
			{
				$params .= '&' . $key . '=' . $value;
			}
		}

		$data->all = new PaginationObject(Text::_('JLIB_HTML_VIEW_ALL'), $this->prefix);

		if (!$this->viewall)
		{
			$data->all->base = '0';
			$data->all->link = \JRoute::_($params . '&' . $this->prefix . 'limitstart=');
		}

		// Set the start and previous data objects.
		$data->start    = new PaginationObject(Text::_('JLIB_HTML_START'), $this->prefix);
		$data->previous = new PaginationObject(Text::_('JPREV'), $this->prefix);

		if ($this->pagesCurrent > 1)
		{
			$page = ($this->pagesCurrent - 2) * $this->limit;

			// Set the empty for removal from route
			// @todo remove code: $page = $page == 0 ? '' : $page;

			$data->start->base    = '0';
			$data->start->link    = \JRoute::_($params . '&' . $this->prefix . 'limitstart=0');
			$data->previous->base = $page;
			$data->previous->link = \JRoute::_($params . '&' . $this->prefix . 'limitstart=' . $page);
		}

		// Set the next and end data objects.
		$data->next = new PaginationObject(Text::_('JNEXT'), $this->prefix);
		$data->end  = new PaginationObject(Text::_('JLIB_HTML_END'), $this->prefix);

		if ($this->pagesCurrent < $this->pagesTotal)
		{
			$next = $this->pagesCurrent * $this->limit;
			$end  = ($this->pagesTotal - 1) * $this->limit;

			$data->next->base = $next;
			$data->next->link = \JRoute::_($params . '&' . $this->prefix . 'limitstart=' . $next);
			$data->end->base  = $end;
			$data->end->link  = \JRoute::_($params . '&' . $this->prefix . 'limitstart=' . $end);
		}

		$data->pages = array();
		$stop        = $this->pagesStop;

		for ($i = $this->pagesStart; $i <= $stop; $i++)
		{
			$offset = ($i - 1) * $this->limit;

			$data->pages[$i] = new PaginationObject($i, $this->prefix);

			if ($i != $this->pagesCurrent || $this->viewall)
			{
				$data->pages[$i]->base = $offset;
				$data->pages[$i]->link = \JRoute::_($params . '&' . $this->prefix . 'limitstart=' . $offset);
			}
			else
			{
				$data->pages[$i]->active = true;
			}
		}

		return $data;
	}
}
