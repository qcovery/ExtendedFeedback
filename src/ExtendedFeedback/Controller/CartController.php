<?php
/**
 * Cart Controller
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace ExtendedFeedback\Controller;

use VuFind\Controller\CartController as BasicCartController;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;

use function count;
use function is_array;

/**
 * Cart Controller
 *
 * Extends the VuFind cart email action by appending MLA citations of the
 * selected records to the email message.
 *
 * @category VuFind
 * @package  Controller
 * @author   Josiah Knoll <jk1135@ship.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CartController extends BasicCartController
{
    /**
     * Email a batch of records.
     *
     * Based on \VuFind\Controller\CartController::emailAction() (VuFind 11).
     *
     * @return mixed
     */
    public function emailAction()
    {
        // Retrieve ID list:
        $ids = $this->getSelectedIds();

        // Retrieve follow-up information if necessary:
        if (!is_array($ids) || empty($ids)) {
            $ids = $this->followup()->retrieveAndClear('cartIds') ?? [];
        }
        $actionLimit = $this->getBulkActionLimit('email');
        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $this->redirectToSource('error', 'bulk_noitems_advice')) {
                return $redirect;
            }
            $submitDisabled = true;
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = $this->translate(
                'bulk_limit_exceeded',
                ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            );
            if ($redirect = $this->redirectToSource('error', $errorMsg)) {
                return $redirect;
            }
            $submitDisabled = true;
        }

        $emailActionSettings = $this->getService(\VuFind\Config\AccountCapabilities::class)->getEmailActionSetting();
        if ($emailActionSettings === 'disabled') {
            throw new ForbiddenException('Email action disabled');
        }
        // Force login if necessary:
        if (
            $emailActionSettings !== 'enabled'
            && !$this->getUser()
        ) {
            return $this->forceLogin(
                null,
                ['cartIds' => $ids, 'cartAction' => 'Email']
            );
        }

        $view = $this->createEmailViewModel(
            null,
            $this->translate('bulk_email_title')
        );
        $view->records = $this->getRecordLoader()->loadBatch($ids);
        // Set up Captcha
        $view->useCaptcha = $this->captcha()->active('email');

        // Process form submission:
        if (!($submitDisabled ?? false) && $this->formWasSubmitted(useCaptcha: $view->useCaptcha)) {
            // Build the URL to share:
            $params = [];
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $url = $this->getServerUrl('records-home') . '?' . implode('&', $params);

            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = $this->getService(\VuFind\Mailer\Mailer::class);
                $mailer->setMaxRecipients($view->maxRecipients);
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                    ? $view->from : null;
                $mailer->sendLink(
                    $view->to,
                    $view->from,
                    ($view->message ?? '') . $this->getCitationsMessage($view->records),
                    $url,
                    $this->getViewRenderer(),
                    $view->subject,
                    $cc
                );
                return $this->redirectToSource('success', 'bulk_email_success', true);
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getDisplayMessage(), 'error');
            }
        }

        return $view;
    }

    /**
     * Qcovery: Generate MLA citations of the given records to add to the email
     * message.
     *
     * @param \VuFind\RecordDriver\AbstractBase[] $records Records
     *
     * @return string
     */
    protected function getCitationsMessage(array $records): string
    {
        $citationHelper = $this->getViewRenderer()->plugin('citation');
        $citations = [];
        foreach ($records as $record) {
            $citations[] = strip_tags($citationHelper($record)->getCitation('MLA'));
        }
        return $citations ? "\n\n" . implode("\n\n", $citations) : '';
    }
}
