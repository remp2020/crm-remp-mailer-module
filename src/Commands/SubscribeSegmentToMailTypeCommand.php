<?php

namespace Crm\RempMailerModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\UnexpectedValueException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SubscribeSegmentToMailTypeCommand extends Command
{
    use DecoratedCommandTrait;

    public function __construct(
        private MailTypesRepository $mailTypesRepository,
        private MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private SegmentFactoryInterface $segmentFactory,
        private UsersRepository $usersRepository,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('remp-mailer:subscribe-segment-to-mail-type')
            ->setDescription('Subscribe users from segment to mail type')
            ->addOption(
                'segment',
                's',
                InputOption::VALUE_REQUIRED,
                'Code of segment which contains users this command should subscribe to provided mail type',
            )
            ->addOption(
                'mail-type',
                'm',
                InputOption::VALUE_REQUIRED,
                'Mail type code to subscribe users to',
            )
            ->addUsage("--segment=active_registered_users --mail-type=svetovy_newsfilter")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $segmentCode = $input->getOption('segment');
        if ($segmentCode === null) {
            $this->error("No segment code provided.");
            return Command::FAILURE;
        }

        $mailTypeCode = $input->getOption('mail-type');
        if ($mailTypeCode === null) {
            $this->error("No mail type code provided.");
            return Command::FAILURE;
        }

        try {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
        } catch (UnexpectedValueException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        $mailType = $this->mailTypesRepository->getByCode($mailTypeCode);
        if ($mailType === null) {
            $this->error("Mail type with code [{$mailTypeCode}] doesn't exist.");
            return Command::FAILURE;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "This command will subscribe <info>{$segment->totalCount()} users</info> belonging to <info>[{$segmentCode}]</info> segment to mail type <info>{$mailType->title} - [{$mailTypeCode}]</info>. Continue? ",
            false
        );
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        $this->line("");
        $this->line("Subscribing:");

        $subscribed = 0;
        $alreadySubscribed = 0;
        $requests = [];

        $segment->process(function ($user) use ($mailType, &$subscribed, &$alreadySubscribed, &$requests) {
            $userPreferences = $this->mailUserSubscriptionsRepository->userPreferences($user->id);
            $isSubscribed = $userPreferences[$mailType->id]['is_subscribed'] ?? false;

            if ($isSubscribed) {
                $alreadySubscribed++;
                $this->line(" * {$user->email} - <comment>ALREADY SUBSCRIBED</comment>");
                return;
            }

            if (isset($userPreferences[$mailType->id]) && $userPreferences[$mailType->id]['updated_at'] !== $userPreferences[$mailType->id]['created_at']) {
                // if user made a change in the mail subscription in the past
                $alreadySubscribed++;
                $this->line(" * {$user->email} - <comment>ALREADY UNSUBSCRIBED MANUALLY</comment>");
                return;
            }

            $userRow = $this->usersRepository->find($user->id);

            $request = new MailSubscribeRequest();
            $request->setUser($userRow);
            $request->setMailTypeId($mailType->id);
            $request->setMailTypeCode($mailType->code);
            $request->setSendAccompanyingEmails(false);
            $request->setSubscribed(true);

            $requests[] = $request;

            $this->line(" * {$user->email} - <info>SUBSCRIBING</info>");
            $subscribed++;
        });

        $output->write("Executing bulk subscribe: ");
        $this->mailUserSubscriptionsRepository->bulkSubscriptionChange($requests);
        $output->writeln("OK");

        $this->line("");
        $this->line("<comment>{$alreadySubscribed} users</comment> already subscribed.");
        $this->line("<comment>{$subscribed} users</comment> subscribed by command.");
        $this->line("");
        $this->line("Done.");

        return Command::SUCCESS;
    }
}
