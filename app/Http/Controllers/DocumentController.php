<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChatGPTJob;
use App\Models\DocumentAnalytics;
use App\Models\Contract\MitigationMeasure;
use App\Models\Contract\RiskDefinition;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function assessmentRisk(Request $request)
    {
        try {
            set_time_limit(0);

            $content = $request->content;

            $prompts = "The following text is about a contract. \n" .
                "1. You have to do the below work according to several criteria such as 'business', 'legal', 'compliance', 'financial', 'technical', and 'reputation'.\n" .
                "2. Your work for each criteria is to :\n" .
                "1). find the risk-indicative sentence from the original document. \n" .
                "2). make a brief risk definition regrading the searched risk-indicative sentence.\n" .
                "3). assess the 'probability' and 'impact on client' regarding the risk-indicative sentence. (On 0-100 scoring system respectively.)\n" .
                "4). suggest mitigation measures for the respective risky clause, on how to change the risky clause. (You should provide 1~2 mitigation measures for the particular risk.)\n" .
                "5). assess the 'probability after mitigation' for the risk-indicative sentence. (On 0-100 scoring system respectively.)\n" .
                "6). assess the 'impact after mitigation' for the risk-indicative sentence. (On 0-100 scoring system respectively.)\n" .
                "7). finally assess the 'average risk score before and after mitigation with only number according to the previous scores'. (it will be float number.)\n" .
                "3. You have to make a pair that is containing 6 patterns.\n" .
                "1) Each pattern starts with '!!!' and ends with '+++'.\n" .
                "2) In each pattern, !!!Criteria@@@Risk-indicative sentence%*%Brief risk definition###probability##!+Impact on Client!@Mitigation Measure!#Probability after mitigation!&*Impact after Mitigation#**Average risk score before and after mitigation+++.\n" .

                "Remember, each criteria mustn't be repeated. And the risk-indicative sentence must be completely same with the original document's. Please give me only 'these patterns' without any explanations or comments. \nThe following text is the contract document:\n " . $content;

            $data['result'] = $this->assessRiskWithChatGPT($prompts);

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function assessRiskWithChatGPT($prompt)
    {
        try {
            $messageChunks = str_split($prompt, 4096); // Split the message into smaller chunks

            $batchSize = 4; // Number of requests to send in each batch
            $numChunks = count($messageChunks);
            $numBatches = ceil($numChunks / $batchSize);

            $rawText = '';

            $batchMessages = [];

            for ($batchIndex = 0; $batchIndex < $numBatches; $batchIndex++) {
                $batchStartIndex = $batchIndex * $batchSize;
                $batchEndIndex = min(($batchIndex + 1) * $batchSize, $numChunks);
                $batchChunks = array_slice($messageChunks, $batchStartIndex, $batchEndIndex - $batchStartIndex);

                $batchMessages[] = array_map(function ($chunk) {
                    return [
                        'role' => 'user',
                        'content' => $chunk
                    ];
                }, $batchChunks);
            }

            $combinedMessages = array_merge(...$batchMessages);

            $data = OpenAI::chat()->create([
                'model' => 'gpt-4-1106-preview',
                'messages' => $combinedMessages
            ]);

            foreach ($data['choices'] as $choice) {
                $rawText .= $choice['message']['content'];
            }

            $uniqueId = uniqid();

            $result = $this->extractDataFromPattern($rawText, $uniqueId);

            $dbData = $this->saveToDB($result, $uniqueId);

            return response()->json($dbData, 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getChatGptJobResult()
    {
        $response = '';

        return response()->json(['rawText' => $response], 200);
    }

    function extractDataFromPattern($pattern, $uniqueId)
    {
        try {
            $total = [];
            $patterns = [];

            preg_match_all('/!!!\s*(.*?)\s*\+\+\+/', $pattern, $matches);

            if (isset($matches[1])) {
                $patterns = $matches[1];
            }

            for ($index = 0; $index < count($patterns); $index++) {
                $subPattern = [];

                $category = explode("@@@", $patterns[$index])[0];

                // to extract risky sentence...
                $riskySentence = "";
                $patternForRiskySentence = '/@@@(.*?)\%\*\%/s';
                preg_match($patternForRiskySentence, $patterns[$index], $matches_risky);
                if (isset($matches_risky)) {
                    $riskySentence = trim($matches_risky[1]);
                } else {
                    $riskySentence = "";
                }

                // to extract risk definition...
                $riskDefinition = "";
                $patternForRiskDefinition = '/\%\*\%(.*?)\#+/s';
                preg_match($patternForRiskDefinition, $patterns[$index], $matches_definition);
                if (isset($matches_definition)) {
                    $riskDefinition = trim($matches_definition[1]);
                } else {
                    $riskDefinition = "";
                }

                // to extract probability...
                $probability = "";
                $patternForProbability = '/\#{3}(.*?)\#{2}\!+/s';
                preg_match($patternForProbability, $patterns[$index], $matches_probability);
                if (isset($matches_probability)) {
                    $probability = trim($matches_probability[1]);
                } else {
                    $probability = "";
                }

                // to extract impact on client...
                $impactOnClient = "";
                $patternForImpactOnClient = '/\#{2}\!\+(.*?)\!\@/s';
                preg_match($patternForImpactOnClient, $patterns[$index], $matches_impactOnClient);
                if (isset($matches_impactOnClient)) {
                    $impactOnClient = trim($matches_impactOnClient[1]);
                } else {
                    $impactOnClient = "";
                }

                // to extract mitigation measure...
                $mitigationMeasure = "";
                $patternForMitigation = '/\!\@(.*?)\!\#/s';
                preg_match($patternForMitigation, $patterns[$index], $matches_mitigationMeasure);
                if (isset($matches_mitigationMeasure)) {
                    $mitigationMeasure = trim($matches_mitigationMeasure[1]);
                } else {
                    $mitigationMeasure = "";
                }

                // to extract probability after mitigation...
                $probabilityAfterMitigation = "";
                $patternForProbabilityAfterMitigation = '/\!\#(.*?)\!\&\*/s';
                preg_match($patternForProbabilityAfterMitigation, $patterns[$index], $matches_probabilityAfter);
                if (isset($matches_probabilityAfter)) {
                    $probabilityAfterMitigation = trim($matches_probabilityAfter[1]);
                } else {
                    $probabilityAfterMitigation = "";
                }

                // to extract impact after mitigation...
                $impactAfterMitigation = "";
                $patternForImpactAfterMitigation = '/\!\&\*(.*?)\#*\*\*/s';
                preg_match($patternForImpactAfterMitigation, $patterns[$index], $matches_impactAfter);
                if (isset($matches_impactAfter)) {
                    $impactAfterMitigation = trim($matches_impactAfter[1]);
                } else {
                    $impactAfterMitigation = "";
                }

                // to extract average risk score...
                $averageRiskScore = "";
                $patternForAverageRiskScore = '/\#\*\*(.*?)\+\+\+/s';
                preg_match($patternForImpactAfterMitigation, $patterns[$index], $matches_averageRiskScore);
                if (isset($matches_averageRiskScore)) {
                    $averageRiskScore = trim($matches_averageRiskScore[1]);
                } else {
                    $averageRiskScore = "";
                }


                $subPattern['user_id'] = auth()->user()->id;
                $subPattern['file_name'] = $uniqueId;
                $subPattern['category'] = $category;
                $subPattern['risky_sentence'] = $riskySentence;
                $subPattern['risk_definition'] = $riskDefinition;
                $subPattern['probability'] = $probability;
                $subPattern['impact_on_client'] = $impactOnClient;
                $subPattern['mitigation_measure'] = $mitigationMeasure;
                $subPattern['probability_after_mitigation'] = $probabilityAfterMitigation;
                $subPattern['average_risk_score'] = $averageRiskScore;

                $total[] = $subPattern;
            }

            return $total;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function saveToDB($data, $uniqueId)
    {
        try {
            if(count($data) == 0) {
                return "length is 0";
            }

            for ($index = 0; $index < count($data); $index++) {
                $tmpItem = DocumentAnalytics::create([
                    'user_id' => $data[$index]['user_id'],
                    'file_name' => $data[$index]['file_name'],
                    'category' => $data[$index]['category'],
                    'risky_sentence' => $data[$index]['risky_sentence'],
                    'risk_definition' => $data[$index]['risk_definition'],
                    'probability' => $data[$index]['probability'],
                    'risk_definition' => $data[$index]['risk_definition'],
                    'impact_on_client' => $data[$index]['impact_on_client'],
                    'mitigation_measure' => $data[$index]['mitigation_measure'],
                    'probability_after_mitigation' => $data[$index]['probability_after_mitigation'],
                    'average_risk_score' => $data[$index]['average_risk_score']
                ]);

                // saving data to mitigation_measures table...
                MitigationMeasure::create([
                    'document_analytics_id' => $tmpItem->id,
                    'mitigation_measure' => $data[$index]['mitigation_measure'],
                    'thumbs' => ''
                ]);

                // saving data to risk_definitions table...
                RiskDefinition::create([
                    'document_analytics_id' => $tmpItem->id,
                    'risk_definition' => $data[$index]['risk_definition'],
                    'thumbs' => ''
                ]);
            }

            // This data is from document_analytics table basically...
            $initialData = DB::table('document_analytics')
                ->where('user_id', '=', auth()->user()->id)
                ->where('file_name', '=', $uniqueId)
                ->get()
                ->all();

            return $initialData;
        } catch (\Exception $e) {
            return  $e->getMessage();
        }
    }

    public function setVote(Request $request)
    {
        try {
            $mode = $request->mode;
            $document = DocumentAnalytics::where('id', '=', $request->id)->get()->first();
            $updatedData = "";
            $data = [];

            if ($mode == "risk_definition") {
                $updatedData = RiskDefinition::where('document_analytics_id', '=', $request->id)
                    ->latest()
                    ->first()
                    ->update([
                        'thumbs' => $request->isUp
                    ]);

                $data["mode"] = "risk_definition";
                $data["up_down"] = $request->isUp;
            } else if ($mode == "mitigation_measure") {
                $updatedData = MitigationMeasure::where('document_analytics_id', '=', $request->id)
                    ->latest()
                    ->first()
                    ->update([
                        'thumbs' => $request->isUp
                    ]);

                $data["mode"] = "mitigation_measure";
                $data["up_down"] = $request->isUp;
            }

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getVote(Request $request)
    {
        try {
            $data = [];
            $mode = $request->mode;

            if ($mode == "risk_definition") {
                $data["up_down"] = RiskDefinition::where("document_analytics_id", "=", $request->id)
                    ->get()
                    ->first()
                    ->thumbs;
            } else if ($mode == "mitigation_measure") {
                $data["up_down"] = MitigationMeasure::where("document_analytics_id", "=", $request->id)
                    ->get()
                    ->first()
                    ->thumbs;
            }

            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }


    public function regenerateText(Request $request)
    {
        try {
            $riskySentence = DocumentAnalytics::where('id', '=', $request->document_analytics_id)
                ->get()
                ->first()
                ->risky_sentence;
            $riskDefinition = DocumentAnalytics::where("id", "=", $request->document_analytics_id)
                ->latest()
                ->first()
                ->risk_definition;
            $mitigationMeasure = DocumentAnalytics::where("id", "=", $request->document_analytics_id)
                ->latest()
                ->first()
                ->mitigation_measure;

            $mode = $request->mode;
            $messages = "";
            $generatedMessage = "";

            if ($mode == "risk_definition") {
                $messages = "The following sentence is a risky sentence that was assessed in view of " . $request->category . " in a contract document.\n Risky sentence: <" . $riskySentence . ">.\n Then the following sentence is the current risk definition about the risky sentence.\n Risk definition: <" . $riskDefinition . ">.\n You have to assess the risk definition again. Please give me only the reassessed risk definition. Thank you.";
            } else if ($mode == "mitigation_measure") {
                $messages = "The following sentence is a risky sentence that was assessed in view of " . $request->category . " in a contract document.\n Risky sentence: <" . $riskySentence . ">.\n Then the following sentence is the current mitigation measure for the risky sentence.\n Mitigation measure: <" . $mitigationMeasure . ">.\n You have to measure the mitigation again. Please give me only the regenerated mitigation measure. Thank you.";
            }

            $result = OpenAI::chat()->create([
                'model' => 'gpt-4-1106-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $messages
                    ],
                ]
            ]);

            $newResult = $result->choices[0]->message->content;

            $isSavedToDB = "";

            // newly generated result for risk definition or mitigation measure...
            if ($mode == "risk_definition") {
                $isSavedToDB = RiskDefinition::create([
                    'document_analytics_id' => $request->document_analytics_id,
                    'risk_definition' => $newResult,
                    'thumbs' => "",
                ]);
            } else if ($mode == "mitigation_measure") {
                $isSavedToDB = MitigationMeasure::create([
                    'document_analytics_id' => $request->document_analytics_id,
                    'mitigation_measure' => $newResult,
                    'thumbs' => "",
                ]);
            }

            if ($isSavedToDB) {
                return response()->json($isSavedToDB, 200);
            } else {
                return response()->json("saving failed!", 500);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function handleTrackChanges(Request $request)
    {
        try {
            $document = DocumentAnalytics::where('id', '=', $request->document_analytics_id)->get()->first();

            $prompt = "I have a risk indicative sentence in a legal contract document. Please find the 'wrong portion' of the sentence and give me the 'other words' so that I can replace with the 'wrong portion'. Please give me the only 'wrong portion' and 'other words'. \nGive me only one pattern like 'wrong portion@@@other words' without any explanation and without any special symbols except for @@@. So there must be only one '@@@' symbol in the pattern.
            \n risk indicative sentence: <" . $document->risky_sentence . ">";

            $result = OpenAI::chat()->create([
                'model' => 'gpt-4-1106-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ]
            ]);

            $newResult = $result->choices[0]->message->content;

            return response()->json($newResult, 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getAllRiskySentences(Request $request)
    {
        try {
            $prompt = "I have a contract document.\n " .
                "1. You have to find all of the risky clauses from the following contract text. \n" .

                "2. Your work for each criteria is to : \n" .
                "1). find all of the risk-indicative sentences with it's related criteria from the original document. \n" .
                "2). suggest mitigation measures for the respective risky clause, on how to change the contract to prevent the risky clause. \n" .

                "3. You have to make a pair. \n" .
                "1) Each pattern starts with '!!!' and ends with '+++'. \n" .
                "2) In each pattern, !!!Criteria@@@Risk-indicative sentence###Mitigation Measure+++. \n" .

                "Remember, the risk-indicative sentence must be original document's sentence completely. Please give me this pairs. I need only this pairs, don't send me anything else without the pairs. \n The following is the contract document: \n" . $request->content;

            $result = OpenAI::chat()->create([
                'model' => 'gpt-4-1106-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ]
            ]);

            $newResult = $result->choices[0]->message->content;
            return response()->json($newResult, 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }


    public function copilotTrackChanges(Request $request)
    {
        try {
            $values = $this->controlCopilotTrackChanges($request);

            return response()->json($values, 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function controlCopilotTrackChanges($params)
    {
        try {
            $clause = $params->clause;
            $mitigation = $params->mitigation;
            $category = $params->category;

            $prompt = "I have a risk indicative sentence in a legal contract document. Please find the 'wrong portion' of the sentence and give me the 'other words' so that I can replace with the 'wrong portion'. Please give me the only 'wrong portion' and 'other words'. \nGive me only one pattern like 'wrong portion@@@other words' without any explanation and without any special symbols except for @@@. So there must be only one '@@@' symbol in the pattern.
            \n risk indicative sentence: <" . $clause . ">";

            $result = OpenAI::chat()->create([
                'model' => 'gpt-4-1106-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ]
            ]);

            $newResult = $result->choices[0]->message->content;

            return $newResult;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
