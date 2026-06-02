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
        private readonly MailTypesRepository $mailTypesRepository,
        private readonly MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private readonly SegmentFactoryInterface $segmentFactory,
        private readonly UsersRepository $usersRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
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
            ->addOption(
                'variant-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional mail type variant code to subscribe users to (must belong to the provided mail type)',
            )
            ->addOption(
                'chunk-size',
                'c',
                InputOption::VALUE_REQUIRED,
                'Number of users to subscribe per API request (default: 1000)',
                1000,
            )
            ->addUsage("--segment=active_registered_users --mail-type=svetovy_newsfilter")
            ->addUsage("--segment=active_registered_users --mail-type=svetovy_newsfilter --variant-code=svetovy_newsfilter_morning")
            ->addUsage("--segment=active_registered_users --mail-type=svetovy_newsfilter --chunk-size=500")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        $variantCode = $input->getOption('variant-code');

        try {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
        } catch (UnexpectedValueException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        $mailType = $this->mailTypesRepository->getByCode($mailTypeCode, includeVariantsData: $variantCode !== null);
        if ($mailType === null) {
            $this->error("Mail type with code [{$mailTypeCode}] doesn't exist.");
            return Command::FAILURE;
        }

        $mailTypeVariant = null;
        if ($variantCode !== null) {
            foreach ($mailType->variants as $variant) {
                if ($variant->code === $variantCode) {
                    $mailTypeVariant = $variant;
                    break;
                }
            }

            if ($mailTypeVariant === null) {
                $this->error("Variant with code [{$variantCode}] doesn't exist for mail type [{$mailTypeCode}].");
                return Command::FAILURE;
            }
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $variantInfo = $mailTypeVariant !== null ? " variant <info>{$mailTypeVariant->code}</info>" : "";
        $question = new ConfirmationQuestion(
            "This command will subscribe <info>{$segment->totalCount()} users</info> belonging to <info>[{$segmentCode}]</info> segment to mail type <info>{$mailType->title} - [{$mailTypeCode}]</info>{$variantInfo}. Continue? ",
            false,
        );
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        $chunkSize = (int) $input->getOption('chunk-size');
        $subscribed = 0;
        $failed = 0;
        $alreadySubscribed = 0;
        $requests = [];
        $chunkNumber = 0;

        $this->line("Subscribing, requests to Mailer will be sent every <comment>{$chunkSize}</comment> new subscribers.");

        $segment->process(function ($user) use ($mailType, $mailTypeVariant, $chunkSize, &$subscribed, &$failed, &$alreadySubscribed, &$requests, &$chunkNumber) {
            $userPreferences = $this->mailUserSubscriptionsRepository->userPreferences($user->id);
            $isSubscribed = $userPreferences[$mailType->id]['is_subscribed'] ?? false;

            if ($mailTypeVariant) {
                $isSubscribed = isset($userPreferences[$mailType->id][$mailTypeVariant->id]);
            }

            if ($isSubscribed) {
                $alreadySubscribed++;
                $this->line(" * {$user->email} - SKIPPED (already subscribed)");
                return;
            }

            if (isset($userPreferences[$mailType->id]) && $userPreferences[$mailType->id]['updated_at'] !== $userPreferences[$mailType->id]['created_at']) {
                // if user made a change in the mail subscription in the past
                $alreadySubscribed++;
                $this->line(" * {$user->email} - SKIPPED (already unsubscribed manually)");
                return;
            }

            $userRow = $this->usersRepository->find($user->id);

            $request = new MailSubscribeRequest();
            $request->setUser($userRow);
            $request->setMailTypeId($mailType->id);
            $request->setMailTypeCode($mailType->code);
            $request->setSendAccompanyingEmails(false);
            $request->setSubscribed(true);
            if ($mailTypeVariant === null) {
                $request->setForceNoVariantSubscription(true);
            }

            if ($mailTypeVariant !== null) {
                $request->setVariantId($mailTypeVariant->id);
                $request->setVariantCode($mailTypeVariant->code);
            }

            $requests[] = $request;

            $this->line(" * {$user->email} - <comment>WILL SUBSCRIBE</comment>");

            if (count($requests) >= $chunkSize) {
                $chunkNumber++;
                $this->output->write("SENDING BULK SUBSCRIBE #{$chunkNumber}: ");
                $result = $this->mailUserSubscriptionsRepository->bulkSubscriptionChange($requests);

                if ($result) {
                    $subscribed += count($requests);
                    $this->output->writeln("<info>OK</info>");
                } else {
                    $failed += count($requests);
                    $this->output->writeln("<error>FAILED</error>");
                }

                $requests = [];
            }
        });

        if (count($requests) > 0) {
            $chunkNumber++;
            $this->output->write("SENDING BULK SUBSCRIBE #{$chunkNumber}: ");
            $result = $this->mailUserSubscriptionsRepository->bulkSubscriptionChange($requests);

            if ($result) {
                $subscribed += count($requests);
                $this->output->writeln("OK");
            } else {
                $failed += count($requests);
                $this->output->writeln("<error>FAILED</error>");
            }
        }

        $this->line("");
        $this->line("<comment>{$alreadySubscribed} users</comment> already subscribed.");
        $this->line("<comment>{$subscribed} users</comment> subscribed by command.");
        $this->line("<comment>{$failed} users</comment> NOT subscribed by command.");
        $this->line("");
        $this->line("Done.");

        return Command::SUCCESS;
    }
}
