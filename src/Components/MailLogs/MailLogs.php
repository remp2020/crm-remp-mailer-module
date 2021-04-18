<?php

namespace Crm\RempMailerModule\Components\MailLogs;

use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\RempMailerModule\Models\Api\MailLogQueryBuilder;
use Crm\RempMailerModule\Repositories\MailLogsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Control;
use Nette\Utils\Html;

class MailLogs extends Control implements WidgetInterface
{
    private $view = 'mail_logs';

    private $usersRepository;

    private $translator;

    private $logQuery;

    private $mailLogRepository;

    private $totalCount;

    /** @var VisualPaginator */
    private $paginator;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    public function __construct(
        MailLogQueryBuilder $logQueryBuilder,
        MailLogsRepository $mailLogRepository,
        UsersRepository $usersRepository,
        UnclaimedUser $unclaimedUser,
        Translator $translator
    ) {
        parent::__construct();
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
        $this->logQuery = $logQueryBuilder;
        $this->mailLogRepository = $mailLogRepository;
        $this->unclaimedUser = $unclaimedUser;
    }

    public function header($id = '')
    {
        $header = $this->translator->translate('remp_mailer.admin.mail_logs_component.header');
        if ($id) {
            $header .= Html::el('small')->setHtml(' (' . $this->totalCount($id) . ')');
        }
        return $header;
    }

    public function identifier()
    {
        return 'mailer_useremails';
    }

    public function setPaginator(VisualPaginator $paginator)
    {
        $this->paginator = $paginator;
    }

    public function render($userId)
    {
        if (!isset($this->template->filter)) {
            $this->template->filter = null;
        }

        $user = $this->usersRepository->find($userId);
        if (!$user->active || $this->unclaimedUser->isUnclaimedUser($user)) {
            $total = 0;
        } else {
            $total = $this->totalCount($userId);

            if ($this->paginator) {
                $paginator = $this->paginator->getPaginator();
                $paginator->setItemCount($total);
                $paginator->setItemsPerPage(10);
                $this->logQuery->setLimit($paginator->getLength())->setPage($paginator->getPage());
            }

            $this->logQuery->setEmail($user->email);

            $logs = $this->mailLogRepository->get($this->logQuery);
            $counts = $this->mailLogRepository->count($user->email, [
                'delivered_at',
                'clicked_at',
                'opened_at',
                'dropped_at',
                'spam_complained_at',
                'hard_bounced_at',
            ]);
        }

        $this->template->emails = $logs ?? [];
        $this->template->totals = [
            'total' => $total,
            'delivered' => $counts['delivered_at'] ?? 0,
            'clicked' => $counts['clicked_at'] ?? 0,
            'opened' => $counts['opened_at'] ?? 0,
            'dropped' => $counts['dropped_at'] ?? 0,
            'spam_complained' => $counts['spam_complained_at'] ?? 0,
            'hard_bounced' => $counts['hard_bounced_at'] ?? 0,
        ];

        $this->template->setFile(__DIR__ . '/' . $this->view . '.latte');
        $this->template->render();
    }

    private function totalCount($userId)
    {
        if ($this->totalCount === null) {
            $user = $this->usersRepository->find($userId);
            $counts = $this->mailLogRepository->count($user->email, ['sent_at']);
            $this->totalCount = $counts['sent_at'] ?? 0;
        }
        return $this->totalCount;
    }

    public function handleFilter($filter)
    {
        $this->template->filter = $filter;
        if ($filter === 'delivered') {
            $this->logQuery->setFilter('delivered_at');
        } elseif ($filter === 'clicked') {
            $this->logQuery->setFilter('clicked_at');
        } elseif ($filter === 'opened') {
            $this->logQuery->setFilter('opened_at');
        } elseif ($filter === 'dropped') {
            $this->logQuery->setFilter('dropped_at');
        } elseif ($filter === 'spam_complained') {
            $this->logQuery->setFilter('spam_complained_at');
        } elseif ($filter === 'hard_bounced') {
            $this->logQuery->setFilter('hard_bounced_at');
        }

        $this->redrawControl('mailLogslisting');
    }
}
