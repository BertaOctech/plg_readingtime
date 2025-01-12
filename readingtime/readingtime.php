<?php
/**
 * @author	  Carlos Cámara <carlos@hepta.es>
 * @copyright Copyright (C) 2012-2020 Hepta Technologies SL. All rights reserved.
 * @url		 https://extensions.hepta.es
 * @license	 GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * ERT plugin.
 * @since 1.0.0
 */
class PlgContentReadingtime extends CMSPlugin
{
	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 * @since  3.5
	 */
	protected $app;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var	boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/**
	 * Prepare content before showing
	 *
	 * @param	string	$context	App context
	 * @param	object	$row		Row object with article data
	 * @param	object	$params		Params object
	 * @param	int		$page		Page number
	 *
	 * @return	Data
	 */
	public function onContentBeforeDisplay($context, &$row, &$params, $page=0)
	{
		$k2PermittedContext = array('com_k2.itemlist', 'com_k2.item');
		$contentPermittedContext = array('com_content.category', 'com_content.featured', 'com_content.article');

		$permittedContext = array_merge($k2PermittedContext, $contentPermittedContext);

		if (in_array($context, $permittedContext))
		{
			$html = '';

			// Get Params
			if (in_array($context, $k2PermittedContext))
			{
				$excludedCategories = $this->params->def('k2excludedcategories', array());

				if (in_array($row->catid, $excludedCategories))
				{
					return '';
				}
			}
			else
			{
				$excludedCategories = $this->params->def('excludedcategories', array());

				if (in_array($row->catid, $excludedCategories))
				{
					return '';
				}
			}

			// Word per minute
			$lowRate = 200;
			$highRate = 400;

			if (!isset($row->fulltext) && isset($row->id))
			{
				$db = Factory::getDbo();
				$query = "SELECT `fulltext` FROM #__content WHERE id=" . $row->id;
				$db->setQuery($query);
				$fullText = $db->loadResult();

				$fullArticle = $row->introtext . " " . $fullText;
			}
			else
			{
				$fullArticle = $row->introtext . " " . $row->fulltext;
			}

			if (!function_exists('mb_str_word_count'))
			{
				include dirname(__FILE__) . '/libraries/string.php';
			}

			$countWords = mb_str_word_count(strip_tags($fullArticle));

			$slowTime = ceil($countWords / $lowRate);
			$quickTime = ceil($countWords / $highRate);

			if ($this->params->def('default-style', '1'))
			{
				$customStyle = "font-weight:bold;";
			}
			else
			{
				$customStyle = $this->params->def('custom-style', '');
			}

			// Render plugin
			$path = PluginHelper::getLayoutPath('content', 'readingtime');
			ob_start();
			include $path;
			$html = ob_get_clean();

			$readingTimeData = new stdClass;
			$readingTimeData->slowtime = $slowTime;
			$readingTimeData->quicktime = $quickTime;
			$readingTimeData->wordCount = $countWords;
			$readingTimeData->formattedtime = $html;

			$row->readingtime = $readingTimeData;

			if (!$this->params->def('hideoutput', '0'))
			{
				return $html;
			}
		}

		return;
	}

	/**
	 * Prepare content
	 *
	 * @param	string	$context	App context
	 * @param	object	$row		Row object with article data
	 * @param	object	$params		Params object
	 * @param	int		$page		Page number
	 *
	 * @return	void
	 */
	public function onContentPrepare($context, &$row, &$params, $page=0)
	{
		if ($context == 'com_content.article')
		{
			if ($this->params->get('showindicator', '0'))
			{
				$wa = $this->app->getDocument()->getWebAssetManager();
				$wa->useScript('jquery');
				$wa->registerAndUseStyle('plg_content_readingtime', 'plg_content_readingtime/readingprogress.css');
				$wa->registerAndUseScript('plg_content_readingtime', 'plg_content_readingtime/readingprogress.js');

				$indicatorType = $this->params->get('bar_indicator_type', '');

				if ($indicatorType)
				{
					$indicatorBarContext = $this->params->get('bar_indicator_context', '');
					$indicatorBarStriped = ($this->params->get('bar_indicator_striped', '0')) ? 'striped' : '';
					$indicatorBarAnimated = ($this->params->get('bar_indicator_animated', '0')) ? ' active' : '';
					$indicatorLabel = $this->params->get('showindicatorlabel', '0');
				}

				$layout = new JLayoutFile('progressbar', null, array('debug' => false, 'suffixes' => array($indicatorType)));
				$layout->addIncludePaths(JPATH_PLUGINS . '/content/readingtime/layouts');

				$row->text = $layout->render(
					compact('indicatorBarContext', 'indicatorBarStriped', 'indicatorBarAnimated', 'indicatorLabel')
				)
					. '<span id="ert-start"></span>' . $row->text;
			}
		}

		return;
	}

}
