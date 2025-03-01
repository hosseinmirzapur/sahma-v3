<?php

namespace App\Jobs;

use App\Models\EntityGroup;
use App\Models\User;
use App\Services\AiService;
use App\Services\OfficeService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SubmitVoiceToSplitterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public int $retryAfter = 7300;

    private EntityGroup $entityGroup;

    /**
     * Create a new job instance.
     */
    public function __construct(EntityGroup $entityGroup)
    {
        $this->entityGroup = $entityGroup;
        $this->onQueue('voice::submit-to-splitter');
    }

    /**
     * Execute the job.
     * @throws Exception
     * @throws GuzzleException
     */
    public function handle(AiService $aiService, OfficeService $officeService): void
    {
        try {
            if (
                !in_array($this->entityGroup->status, [
                EntityGroup::STATUS_WAITING_FOR_SPLIT,
                EntityGroup::STATUS_WAITING_FOR_MANUAL_PROCESS,
                ])
            ) {
                Log::warning("STT => questionAnswerGroup: #{$this->entityGroup->id} is not ready for get windows");
                return;
            }

            $entityGroup = $this->entityGroup;

            $meta = $entityGroup->meta ?? [];
            if (!isset($meta['windows'])) {
                $content = $entityGroup->getFileData(true);

                if (is_null($content)) {
                    throw new Exception("Failed to get voice date. entityGroup: #$entityGroup->id");
                }
                Log::info("
            STT =>
            entityGroup:#$entityGroup->id is going to submit splitter for get windows
            ");
                $windows = $aiService->getVoiceWindows($content);
                $meta['windows'] = $windows;
                $entityGroup->meta = $meta;
                $entityGroup->save();
                CreateSplitVoiceToEntitiesJob::dispatch($this->entityGroup);
            } else {
                Log::info("STT => entityGroup:#$entityGroup->id already got windows");
            }
        } catch (Exception $e) {
            $this->fail();
            throw new Exception($e->getMessage());
        }
    }

    public function fail(): void
    {
        if ($this->entityGroup->number_of_try >= 3) {
            $this->entityGroup->status = EntityGroup::STATUS_REJECTED;
        } else {
            $this->entityGroup->status = EntityGroup::STATUS_WAITING_FOR_RETRY;
        }
        $this->entityGroup->number_of_try += 1;
        $this->entityGroup->save();
    }
}
