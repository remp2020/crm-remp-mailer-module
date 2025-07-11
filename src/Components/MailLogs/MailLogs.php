<?php

namespace Crm\RempMailerModule\Components\MailLogs;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Components\VisualPaginator\VisualPaginator;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\DetailWidgetInterface;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\RempMailerModule\Models\Api\MailLogQueryBuilder;
use Crm\RempMailerModule\Repositories\MailLogsRepository;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Utils\Html;

class MailLogs extends BaseLazyWidget implements DetailWidgetInterface
{
    private string $view = 'mail_logs';

    private VisualPaginator $paginator;

    private int $totalCount;

    private bool $enabled = true;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private MailLogQueryBuilder $logQuery,
        private MailLogsRepository $mailLogRepository,
        private UsersRepository $usersRepository,
        private UnclaimedUser $unclaimedUser,
        private Translator $translator,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function header($id = ''): string
    {
        $header = $this->translator->translate('remp_mailer.admin.mail_logs_component.header');

        $user = $this->usersRepository->find($id);
        if ($user->deleted_at !== null || $this->unclaimedUser->isUnclaimedUser($user)) {
            $this->enabled = false;
            return $header;
        }

        $header .= Html::el('small')->setHtml(' (' . $this->totalCount($id) . ')');
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
        if (!$this->enabled) {
            $total = 0;
        } else {
            $total = $this->totalCount($userId);

            if (isset($this->paginator)) {
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
        $this->template->notLoaded = !$this->enabled;
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
        if (!isset($this->totalCount)) {
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
