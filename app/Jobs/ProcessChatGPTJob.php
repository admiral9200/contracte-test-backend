<?php

namespace App\Jobs;

use App\Events\ChatGpTJobProcessed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class ProcessChatGPTJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $prompt;
    protected $rawText;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct($prompt, $jobId)
    {
        $this->prompt = $prompt;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $messageChunks = str_split($this->prompt, 8192);

        $batchSize = 4;
        $numChunks = count($messageChunks);
        $numBatches = ceil($numChunks / $batchSize);

        $rawText = '';

        for ($batchIndex = 0; $batchIndex < $numBatches; $batchIndex++) {
            $batchStartIndex = $batchIndex * $batchSize;
            $batchEndIndex = ($batchIndex + 1) * $batchSize;
            $batchChunks = array_slice($messageChunks, $batchStartIndex, $batchSize);

            $messages = array_map(function ($chunk) {
                return [
                    'role' => 'user',
                    'content' => $chunk
                ];
            }, $batchChunks);

            $data = OpenAI::chat()->create([
                'model' => 'gpt-4-1106-preview',
                'messages' => $messages
            ]);

            foreach ($data['choices'] as $choice) {
                $rawText .= $choice['message']['content'];
            }
        }

        broadcast(new ChatGpTJobProcessed($this->jobId, $rawText))->toOthers();
    }

    public function getRawText()
    {
        return $this->rawText;
    }
}
