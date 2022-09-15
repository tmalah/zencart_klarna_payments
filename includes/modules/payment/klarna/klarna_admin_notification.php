<?php
/**
 * admin subtemplate for Paypal Website Payments Standard payment method
 *
 * @copyright Copyright 2003-2020 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @copyright Portions Copyright 2004 DevosC.com
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte 2020 May 16 Modified in v1.5.7 $
 */
if (method_exists($this, '_doRefund')) {
          $output = '<table>'."\n";
          $output .= '<tr style="background-color : #cccccc; border: 1px solid black;">'."\n";

          $output .= '<td valign="top">';
          
          $output .= zen_draw_form('klarnaRefund', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doRefund', 'post', '', true) . zen_hide_session_id();
          
          $output .= '<label for="klarnaRefundAmount">Amount to refund: </label><input name="klarnaRefundAmount" size="7" id="klarnaRefundAmount" type="text" value="" /><br />'."\n";
          $output .= '<label for="klarnaRefundDesc">Description: </label><textarea name="klarnaRefundDesc" id="klarnaRefundDesc"></textarea><br />'."\n";
          
          $output .= '<input type="submit" value="Refund Order" />'."\n";
          $output .= '</form>';
          
          $output .= '</td>'."\n";
          
          $output .= '<td valign="top">';
          
          $output .= zen_draw_form('klarnaVoid', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doVoid', 'post', '', true) . zen_hide_session_id();
          
          $output .= '<label for="klarnaVoidDesc">Description: </label><textarea name="klarnaVoidDesc" id="klarnaVoidDesc"></textarea><br />'."\n";
          
          $output .= '<input type="submit" value="Cancel Order" />'."\n";
          $output .= '</form>';
          
          $output .= '</td>'."\n";

          $output .= '</tr>'."\n";
          $output .='</table>'."\n";
}
