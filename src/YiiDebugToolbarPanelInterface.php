<?php
/**
 * @author: Alexander Golovko <alexander.golovko.1989@gmail.com>
 * @created: 16.06.16 13:13
 */


namespace Panoptik\yiidebug;


/**
 * YiiDebugToolbarPanelInterface
 *
 * @author Sergey Malyshev <malyshev.php@gmail.com>
 * @author Igor Golovanov <igor.golovanov@gmail.com>
 * @version $Id$
 * @package YiiDebugToolbar
 * @since 1.1.7
 */
interface YiiDebugToolbarPanelInterface
{
    /**
     * Get the title of menu.
     *
     * @return string
     */
    function getMenuTitle();

    /**
     * Get the subtitle of menu.
     *
     * @return string
     */
    function getMenuSubTitle();

    /**
     * Get the title.
     *
     * @return string
     */
    function getTitle();

    /**
     * Get the subtitle.
     *
     * @return string
     */
    function getSubTitle();
}