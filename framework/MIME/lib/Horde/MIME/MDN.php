<?php
/**
 * The Horde_MIME_MDN:: class implements Message Disposition Notifications as
 * described by RFC 3798.
 *
 * Copyright 2004-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package Horde_MIME
 */
class Horde_MIME_MDN
{
    /**
     * The Horde_MIME_Headers object.
     *
     * @var Horde_MIME_Headers
     */
    protected $_headers;

    /**
     * The text of the original message.
     *
     * @var string
     */
    protected $_msgtext = false;

    /**
     * Constructor.
     *
     * @param Horde_MIME_Headers $mime_headers  A Horde_MIME_Headers object.
     */
    function __construct($headers = null)
    {
        $this->_headers = $headers;
    }

    /**
     * Returns the address to return the MDN to.
     *
     * @return string  The address to send the MDN to. Returns null if no
     *                 MDN is requested.
     */
    public function getMDNReturnAddr()
    {
        /* RFC 3798 [2.1] requires the Disposition-Notificaion-To header
         * for an MDN to be created. */
        return $this->_headers->getValue('Disposition-Notification-To');
    }

    /**
     * Is user input required to send the MDN?
     * Explicit confirmation is needed in some cases to prevent mail loops
     * and the use of MDNs for mail bombing.
     *
     * @return boolean  Is explicit user input required to send the MDN?
     */
    public function userConfirmationNeeded()
    {
        $return_path = $this->_headers->getValue('Return-Path');

        /* RFC 3798 [2.1]: Explicit confirmation is needed if there is no
         * Return-Path in the header. Also, "if the message contains more
         * than one Return-Path header, the implementation may [] treat the
         * situation as a failure of the comparison." */
        if (empty($return_path) || is_array($return_path)) {
            return true;
        }

        require_once dirname(__FILE__) . '/Address.php';

        /* RFC 3798 [2.1]: Explicit confirmation is needed if there is more
         * than one distinct address in the Disposition-Notification-To
         * header. */
        $addr_arr = Horde_MIME_Address::parseAddressList($this->getMDNReturnAddr());
        if (count($addr_arr) > 1) {
            return true;
        }

        /* RFC 3798 [2.1] states that "MDNs SHOULD NOT be sent automatically
         * if the address in the Disposition-Notification-To header differs
         * from the address in the Return-Path header." This comparison is
         * case-sensitive for the mailbox part and case-insensitive for the
         * host part. */
        $ret_arr = Horde_MIME_Address::parseAddressList($return_path);
        return ($addr_arr[0]['mailbox'] == $ret_arr[0]['mailbox']) &&
               (String::lower($addr_arr[0]['host']) == String::lower($ret_arr[0]['host']));
    }

    /**
     * When generating the MDN, should we return the enitre text of the
     * original message?  The default is no - we only return the headers of
     * the original message. If the text is passed in via this method, we
     * will return the entire message.
     *
     * @param string $text  The text of the original message.
     */
    public function originalMessageText($text)
    {
        $this->_msgtext = $text;
    }

    /**
     * Generate the MDN according to the specifications listed in RFC
     * 3798 [3].
     *
     * @param boolean $action   Was this MDN type a result of a manual action
     *                          on part of the user?
     * @param boolean $sending  Was this MDN sent as a result of a manual
     *                          action on part of the user?
     * @param string $type      The type of action performed by the user.
     * <pre>
     * Per RFC 3798 [3.2.6.2] the following types are valid:
     * =====================================================
     * 'displayed'
     * 'deleted'
     * </pre>
     * @param array $mod        The list of modifications.
     * <pre>
     * Per RFC 3798 [3.2.6.3] the following modifications are valid:
     * =============================================================
     * 'error'
     * </pre>
     * @param array $err        If $mod is 'error', the additional information
     *                          to provide.  Key is the type of modification,
     *                          value is the text.
     *
     * @return mixed  True on success, PEAR_Error object on error.
     */
    public function generate($action, $sending, $type, $mod = array(),
                             $err = array())
    {
        require_once dirname(__FILE__) . '/Headers.php';
        require_once dirname(__FILE__) . '/Message.php';
        require_once 'Horde/Identity.php';
        require_once 'Horde/Text.php';

        /* Set up some variables we use later. */
        $identity = &Identity::singleton();
        $from_addr = $identity->getDefaultFromAddress();

        $to = $this->getMDNReturnAddr();
        $ua = $this->_headers->getAgentHeader();

        $orig_recip = $this->_headers->getValue('Original-Recipient');
        if (!empty($orig_recip) && is_array($orig_recip)) {
            $orig_recip = $orig_recip[0];
        }

        $msg_id = $this->_headers->getValue('Message-ID');

        /* Create the Disposition field now (RFC 3798 [3.2.6]). */
        $dispo = 'Disposition: ' .
                 (($action) ? 'manual-action' : 'automatic-action') .
                 '/' .
                 (($sending) ? 'MDN-sent-manually' : 'MDN-sent-automatically') .
                 '; ' .
                 $type;
        if (!empty($mod)) {
            $dispo .= '/' . implode(', ', $mod);
        }

        /* Set up the mail headers. */
        $msg_headers = new Horde_MIME_Headers();
        $msg_headers->addMessageIdHeader();
        $msg_headers->addAgentHeader($ua);
        $msg_headers->addHeader('Date', date('r'));
        $msg_headers->addHeader('From', $from_addr);
        $msg_headers->addHeader('To', $this->getMDNReturnAddr());
        $msg_headers->addHeader('Subject', _("Disposition Notification"));

        /* MDNs are a subtype of 'multipart/report'. */
        $msg = new Horde_MIME_Message();
        $msg->setType('multipart/report');
        $msg->setContentTypeParameter('report-type', 'disposition-notification');

        $charset = NLS::getCharset();

        /* The first part is a human readable message. */
        $part_one = new Horde_MIME_Part('text/plain');
        $part_one->setCharset($charset);
        if ($type == 'displayed') {
            $contents = sprintf(_("The message sent on %s to %s with subject \"%s\" has been displayed.\n\nThis is no guarantee that the message has been read or understood."), $this->_headers->getValue('Date'), $this->_headers->getValue('To'), $this->_headers->getValue('Subject'));
            require_once 'Text/Flowed.php';
            $flowed = new Text_Flowed($contents, $charset);
            $flowed->setDelSp(true);
            $part_one->setContentTypeParameter('format', 'flowed');
            $part_one->setContentTypeParameter('DelSp', 'Yes');
            $part_one->setContents($flowed->toFlowed());
        }
        // TODO: Messages for other notification types.
        $msg->addPart($part_one);

        /* The second part is a machine-parseable description. */
        $part_two = new Horde_MIME_Part('message/disposition-notification');
        $part_two->setContents('Reporting-UA: ' . $GLOBALS['conf']['server']['name'] . '; ' . $ua . "\n");
        if (!empty($orig_recip)) {
            $part_two->appendContents('Original-Recipient: rfc822;' . $orig_recip . "\n");
        }
        $part_two->appendContents('Final-Recipient: rfc822;' . $from_addr . "\n");
        if (!empty($msg_id)) {
            $part_two->appendContents('Original-Message-ID: rfc822;' . $msg_id . "\n");
        }
        $part_two->appendContents($dispo . "\n");
        if (in_array('error', $mod) && isset($err['error'])) {
            $part_two->appendContents('Error: ' . $err['error'] . "\n");
        }
        $msg->addPart($part_two);

        /* The third part is the text of the original message.  RFC 3798 [3]
         * allows us to return only a portion of the entire message - this
         * is left up to the user. */
        $part_three = new Horde_MIME_Part('message/rfc822');
        $part_three->setContents($this->_headers->toString());
        if (!empty($this->_msgtext)) {
            $part_three->appendContents($part_three->getEOL() . $this->_msgtext);
        }
        $msg->addPart($part_three);

        return $msg->send($to, $msg_headers);
    }

    /**
     * Add a MDN (read receipt) request headers to the Horde_MIME_Headers::
     * object.
     *
     * @param Horde_MIME_Headers &$ob  The object to add the headers to.
     * @param string $to               The address the receipt should be
     *                                 mailed to.
     */
    public function addMDNRequestHeaders(&$ob, $to)
    {
        /* This is the RFC 3798 way of requesting a receipt. */
        $ob->addHeader('Disposition-Notification-To', $to);
    }
}
