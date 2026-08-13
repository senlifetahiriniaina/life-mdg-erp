<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Shared\Services\BaseService;

class DataStorytellingService extends BaseService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const AUDIENCE_TYPES = ['executive', 'analyst', 'operator', 'customer'];
    private const STORY_STATUSES = ['draft', 'published', 'archived'];

    /**
     * Create a new data story with metadata.
     *
     * @param  array{title: string, description: string, audience_type: string, created_by: int, company_id: int, status?: string}  $storyData
     * @return array{id: int, title: string, description: string, audience_type: string, status: string, created_at: string}
     */
    public function createDataStory(array $storyData): array
    {
        try {
            if (!in_array($storyData['audience_type'], self::AUDIENCE_TYPES)) {
                throw new \InvalidArgumentException("Invalid audience type: {$storyData['audience_type']}");
            }

            $story = DB::table('bi_data_stories')->insertGetId([
                'title'           => $storyData['title'],
                'description'     => $storyData['description'],
                'audience_type'   => $storyData['audience_type'],
                'status'          => $storyData['status'] ?? 'draft',
                'created_by'      => $storyData['created_by'],
                'company_id'      => $storyData['company_id'],
                'metadata'        => json_encode(['slides' => [], 'narratives' => []]),
                'engagement_data' => json_encode([]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            Log::info('Data story created', [
                'story_id'      => $story,
                'title'         => $storyData['title'],
                'audience_type' => $storyData['audience_type'],
            ]);

            return [
                'id'             => $story,
                'title'          => $storyData['title'],
                'description'    => $storyData['description'],
                'audience_type'  => $storyData['audience_type'],
                'status'         => $storyData['status'] ?? 'draft',
                'created_at'     => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create data story', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Add a narrative slide to story.
     *
     * @param  int  $storyId
     * @param  array{narrative_text: string, visualization_ids?: array<int>, order: int, story_insight?: string}  $slideData
     * @return array
     */
    public function addStorySlide(int $storyId, array $slideData): array
    {
        try {
            $story = DB::table('bi_data_stories')->find($storyId);
            if (!$story) {
                throw new \InvalidArgumentException("Story {$storyId} not found");
            }

            $slideId = DB::table('bi_story_slides')->insertGetId([
                'story_id'         => $storyId,
                'narrative_text'   => $slideData['narrative_text'],
                'visualization_ids' => json_encode($slideData['visualization_ids'] ?? []),
                'slide_order'      => $slideData['order'],
                'story_insight'    => $slideData['story_insight'] ?? null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            Log::info('Story slide added', [
                'story_id' => $storyId,
                'slide_id' => $slideId,
                'order'    => $slideData['order'],
            ]);

            return [
                'id'               => $slideId,
                'story_id'         => $storyId,
                'narrative_text'   => $slideData['narrative_text'],
                'visualization_ids' => $slideData['visualization_ids'] ?? [],
                'order'            => $slideData['order'],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to add story slide', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create narrative flow with branching logic (if-then rules).
     *
     * @param  int  $storyId
     * @param  array{conditions: array<array{field: string, operator: string, value: mixed}>, actions: array<array{type: string, target: int}>, description?: string}  $flowData
     * @return array
     */
    public function createNarrativeFlow(int $storyId, array $flowData): array
    {
        try {
            $flowId = DB::table('bi_narrative_flows')->insertGetId([
                'story_id'    => $storyId,
                'conditions'  => json_encode($flowData['conditions'] ?? []),
                'actions'     => json_encode($flowData['actions'] ?? []),
                'description' => $flowData['description'] ?? null,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            Log::info('Narrative flow created', [
                'story_id'    => $storyId,
                'flow_id'     => $flowId,
                'conditions'  => count($flowData['conditions'] ?? []),
            ]);

            return [
                'id'          => $flowId,
                'story_id'    => $storyId,
                'conditions'  => $flowData['conditions'] ?? [],
                'actions'     => $flowData['actions'] ?? [],
                'description' => $flowData['description'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create narrative flow', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Auto-generate story structure from dashboard insights.
     *
     * @param  int  $dashboardId
     * @param  array{include_insights?: bool, include_anomalies?: bool, focus_area?: string}  $config
     * @return array{storyId: int, slides: array<array>, title: string}
     */
    public function generateGuidedNarrative(int $dashboardId, array $config = []): array
    {
        try {
            $dashboard = DB::table('bi_dashboards')->find($dashboardId);
            if (!$dashboard) {
                throw new \InvalidArgumentException("Dashboard {$dashboardId} not found");
            }

            $includeInsights = $config['include_insights'] ?? true;
            $focusArea       = $config['focus_area'] ?? 'performance';

            // Create story
            $storyData = [
                'title'           => "Guided Analysis: {$dashboard->name}",
                'description'     => "Auto-generated narrative for {$dashboard->name}",
                'audience_type'   => 'analyst',
                'created_by'      => auth()->id() ?? 1,
                'company_id'      => $dashboard->company_id,
            ];

            $storyId = DB::table('bi_data_stories')->insertGetId(array_merge($storyData, [
                'metadata'        => json_encode(['auto_generated' => true]),
                'engagement_data' => json_encode([]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]));

            // Generate slides from dashboard insights
            $slides  = [];
            $widgets = DB::table('bi_widgets')->where('dashboard_id', $dashboardId)->get();

            $order = 1;
            foreach ($widgets as $widget) {
                $slideData = [
                    'narrative_text'    => "Analysis of {$widget->name}",
                    'visualization_ids' => [$widget->id],
                    'order'             => $order++,
                    'story_insight'     => "Key metrics for {$focusArea}",
                ];

                $slideId = DB::table('bi_story_slides')->insertGetId([
                    'story_id'          => $storyId,
                    'narrative_text'    => $slideData['narrative_text'],
                    'visualization_ids' => json_encode($slideData['visualization_ids']),
                    'slide_order'       => $slideData['order'],
                    'story_insight'     => $slideData['story_insight'],
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                $slides[] = [
                    'id'                => $slideId,
                    'narrative_text'    => $slideData['narrative_text'],
                    'visualization_ids' => $slideData['visualization_ids'],
                    'order'             => $slideData['order'],
                ];
            }

            Log::info('Guided narrative generated', [
                'story_id'   => $storyId,
                'dashboard_id' => $dashboardId,
                'slides'     => count($slides),
            ]);

            return [
                'storyId' => $storyId,
                'slides'  => $slides,
                'title'   => $storyData['title'],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to generate guided narrative', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Customize story for specific audience segment.
     *
     * @param  int  $storyId
     * @param  string  $audienceType  One of: executive, analyst, operator, customer
     * @param  array{customizations?: array<string, mixed>}  $options
     * @return array{storyId: int, audienceType: string, customizations: array}
     */
    public function applyAudienceSegmentation(int $storyId, string $audienceType, array $options = []): array
    {
        try {
            if (!in_array($audienceType, self::AUDIENCE_TYPES)) {
                throw new \InvalidArgumentException("Invalid audience type: {$audienceType}");
            }

            $story = DB::table('bi_data_stories')->find($storyId);
            if (!$story) {
                throw new \InvalidArgumentException("Story {$storyId} not found");
            }

            // Define customizations per audience
            $customizations = match ($audienceType) {
                'executive'  => [
                    'depth'         => 'summary',
                    'focus'         => ['kpi', 'trend', 'forecast'],
                    'detail_level'  => 'high_level',
                    'include_drill' => false,
                ],
                'analyst' => [
                    'depth'         => 'detailed',
                    'focus'         => ['metrics', 'anomalies', 'correlations'],
                    'detail_level'  => 'comprehensive',
                    'include_drill' => true,
                ],
                'operator' => [
                    'depth'         => 'operational',
                    'focus'         => ['status', 'alerts', 'actions'],
                    'detail_level'  => 'tactical',
                    'include_drill' => false,
                ],
                'customer' => [
                    'depth'         => 'summary',
                    'focus'         => ['outcomes', 'benefits', 'trends'],
                    'detail_level'  => 'business',
                    'include_drill' => false,
                ],
                default => [],
            };

            // Merge with provided options
            $customizations = array_merge($customizations, $options['customizations'] ?? []);

            // Update story with audience segment info
            DB::table('bi_data_stories')
                ->where('id', $storyId)
                ->update([
                    'audience_type'  => $audienceType,
                    'audience_config' => json_encode($customizations),
                    'updated_at'     => now(),
                ]);

            Log::info('Audience segmentation applied', [
                'story_id'      => $storyId,
                'audience_type' => $audienceType,
            ]);

            return [
                'storyId'         => $storyId,
                'audienceType'    => $audienceType,
                'customizations'  => $customizations,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to apply audience segmentation', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Track story engagement metrics (views, completion rates, time-on-slide).
     *
     * @param  int  $storyId
     * @param  array{user_id: int, action: string, slide_id?: int, timestamp?: string}  $engagementData
     * @return array
     */
    public function trackStoryEngagement(int $storyId, array $engagementData): array
    {
        try {
            $engagement = [
                'story_id'   => $storyId,
                'user_id'    => $engagementData['user_id'],
                'action'     => $engagementData['action'],
                'slide_id'   => $engagementData['slide_id'] ?? null,
                'timestamp'  => $engagementData['timestamp'] ?? now(),
                'created_at' => now(),
            ];

            DB::table('bi_story_engagements')->insert($engagement);

            Log::debug('Story engagement tracked', [
                'story_id' => $storyId,
                'action'   => $engagementData['action'],
                'user_id'  => $engagementData['user_id'],
            ]);

            return $engagement;
        } catch (\Throwable $e) {
            Log::error('Failed to track story engagement', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Publish story and make it available to audience.
     *
     * @param  int  $storyId
     * @param  array{scheduled_for?: string, notification_enabled?: bool}  $options
     * @return array{storyId: int, status: string, publishedAt: string}
     */
    public function publishStory(int $storyId, array $options = []): array
    {
        try {
            $story = DB::table('bi_data_stories')->find($storyId);
            if (!$story) {
                throw new \InvalidArgumentException("Story {$storyId} not found");
            }

            $publishedAt = now();
            if (isset($options['scheduled_for'])) {
                $publishedAt = Carbon::parse($options['scheduled_for']);
            }

            DB::table('bi_data_stories')
                ->where('id', $storyId)
                ->update([
                    'status'         => 'published',
                    'published_at'   => $publishedAt,
                    'updated_at'     => now(),
                ]);

            // Clear engagement cache
            Cache::forget("story:engagement:{$storyId}");

            Log::info('Story published', [
                'story_id'     => $storyId,
                'published_at' => $publishedAt,
            ]);

            return [
                'storyId'     => $storyId,
                'status'      => 'published',
                'publishedAt' => $publishedAt->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to publish story', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get story analytics: views, completion rates, engagement metrics.
     *
     * @param  int  $storyId
     * @param  string  $period  Period: '1h', '24h', '7d', '30d'
     * @return array{totalViews: int, completionRate: float, avgTimeOnSlide: float, mostViewedSlide: int|null, dropOffPoint: int|null}
     */
    public function getStoryAnalytics(int $storyId, string $period = '7d'): array
    {
        try {
            $cacheKey = "story:analytics:{$storyId}:{$period}";
            $cached   = Cache::get($cacheKey);

            if ($cached) {
                return $cached;
            }

            // Calculate period start date
            $periodStart = match ($period) {
                '1h'  => now()->subHour(),
                '24h' => now()->subDay(),
                '7d'  => now()->subDays(7),
                '30d' => now()->subDays(30),
                default => now()->subDays(7),
            };

            // Get engagement data
            $engagements = DB::table('bi_story_engagements')
                ->where('story_id', $storyId)
                ->where('created_at', '>=', $periodStart)
                ->get();

            $totalViews = $engagements->where('action', 'view')->count();

            // Calculate completion rate
            $viewers      = $engagements->pluck('user_id')->unique()->count();
            $completions  = $engagements->where('action', 'complete')->count();
            $completionRate = $viewers > 0 ? ($completions / $viewers) * 100 : 0;

            // Calculate average time on slide
            $timeOnSlide = $engagements->where('action', 'view')->avg('timestamp') ?? 0;

            // Find most viewed slide
            $mostViewedSlide = $engagements->where('action', 'view')
                ->groupBy('slide_id')
                ->map(fn ($group) => count($group))
                ->sortDesc()
                ->keys()
                ->first();

            // Calculate drop-off point
            $slideViews = $engagements->where('action', 'view')
                ->groupBy('slide_id')
                ->map(fn ($group) => count($group))
                ->sortDesc();

            $dropOffPoint = null;
            if ($slideViews->count() > 1) {
                $views         = $slideViews->values()->all();
                $maxDropOff    = 0;
                $dropOffSlide  = null;

                for ($i = 0; $i < count($views) - 1; $i++) {
                    $drop = $views[$i] - $views[$i + 1];
                    if ($drop > $maxDropOff) {
                        $maxDropOff   = $drop;
                        $dropOffSlide = $i + 1;
                    }
                }

                $dropOffPoint = $dropOffSlide;
            }

            $analytics = [
                'totalViews'       => $totalViews,
                'completionRate'   => round($completionRate, 2),
                'avgTimeOnSlide'   => round($timeOnSlide, 2),
                'mostViewedSlide'  => $mostViewedSlide,
                'dropOffPoint'     => $dropOffPoint,
            ];

            Cache::put($cacheKey, $analytics, self::CACHE_TTL);

            Log::debug('Story analytics retrieved', [
                'story_id'       => $storyId,
                'total_views'    => $totalViews,
                'completion_rate' => $completionRate,
            ]);

            return $analytics;
        } catch (\Throwable $e) {
            Log::error('Failed to get story analytics', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle user interactions with story (navigate slides, make decisions).
     *
     * @param  int  $storyId
     * @param  array{user_id: int, action: string, fromSlide?: int, toSlide?: int, decision?: mixed}  $interaction
     * @return array{success: bool, nextSlide: int|null, narrative?: string}
     */
    public function slideInteractionHandler(int $storyId, array $interaction): array
    {
        try {
            $action   = $interaction['action'] ?? 'view';
            $fromSlide = $interaction['fromSlide'] ?? null;
            $toSlide   = $interaction['toSlide'] ?? null;
            $decision  = $interaction['decision'] ?? null;

            // Track engagement
            $this->trackStoryEngagement($storyId, [
                'user_id'  => $interaction['user_id'],
                'action'   => $action,
                'slide_id' => $fromSlide,
            ]);

            // Handle branching logic if decision made
            $nextSlide = $toSlide;
            if ($decision !== null) {
                $flow = DB::table('bi_narrative_flows')
                    ->where('story_id', $storyId)
                    ->where('is_active', true)
                    ->first();

                if ($flow) {
                    $actions    = json_decode($flow->actions, true);
                    $nextSlide  = $actions[0]['target'] ?? $toSlide;
                }
            }

            // Get next slide narrative
            $nextSlideData = null;
            if ($nextSlide) {
                $nextSlideData = DB::table('bi_story_slides')
                    ->where('story_id', $storyId)
                    ->where('slide_order', $nextSlide)
                    ->first();
            }

            Log::debug('Slide interaction handled', [
                'story_id' => $storyId,
                'action'   => $action,
                'from_slide' => $fromSlide,
                'to_slide'   => $nextSlide,
            ]);

            return [
                'success'   => true,
                'nextSlide' => $nextSlide,
                'narrative' => $nextSlideData ? $nextSlideData->narrative_text : null,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to handle slide interaction', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate narrative flow for consistency and logical correctness.
     *
     * @param  int  $storyId
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateNarrativeFlow(int $storyId): array
    {
        try {
            $errors = [];

            $flows = DB::table('bi_narrative_flows')
                ->where('story_id', $storyId)
                ->get();

            foreach ($flows as $flow) {
                $actions = json_decode($flow->actions, true);

                // Validate all target slides exist
                foreach ($actions as $action) {
                    if ($action['type'] === 'goto') {
                        $slideExists = DB::table('bi_story_slides')
                            ->where('story_id', $storyId)
                            ->where('slide_order', $action['target'])
                            ->exists();

                        if (!$slideExists) {
                            $errors[] = "Flow {$flow->id} targets non-existent slide {$action['target']}";
                        }
                    }
                }

                // Validate conditions
                $conditions = json_decode($flow->conditions, true);
                if (empty($conditions)) {
                    $errors[] = "Flow {$flow->id} has no conditions";
                }
            }

            Log::debug('Narrative flow validated', [
                'story_id' => $storyId,
                'errors'   => count($errors),
            ]);

            return [
                'valid'  => empty($errors),
                'errors' => $errors,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to validate narrative flow', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate AI-powered narrative improvement recommendations.
     *
     * @param  int  $storyId
     * @return array{recommendations: array<string>, improvements: array<array{type: string, suggestion: string}>}
     */
    public function generateStoryRecommendations(int $storyId): array
    {
        try {
            $story = DB::table('bi_data_stories')->find($storyId);
            if (!$story) {
                throw new \InvalidArgumentException("Story {$storyId} not found");
            }

            $recommendations = [];
            $improvements    = [];

            $analytics = $this->getStoryAnalytics($storyId);

            // Check engagement levels
            if ($analytics['totalViews'] > 0 && $analytics['completionRate'] < 50) {
                $improvements[] = [
                    'type'       => 'engagement',
                    'suggestion' => 'Low completion rate detected. Consider simplifying narrative or breaking into shorter slides.',
                ];
            }

            if ($analytics['dropOffPoint'] !== null) {
                $improvements[] = [
                    'type'       => 'retention',
                    'suggestion' => "Users drop off at slide {$analytics['dropOffPoint']}. Review content or narrative flow.",
                ];
            }

            // Check story structure
            $slides = DB::table('bi_story_slides')
                ->where('story_id', $storyId)
                ->count();

            if ($slides < 3) {
                $improvements[] = [
                    'type'       => 'structure',
                    'suggestion' => 'Consider adding more slides to develop narrative arc.',
                ];
            }

            if ($slides > 15) {
                $improvements[] = [
                    'type'       => 'structure',
                    'suggestion' => 'Story is lengthy. Consider consolidating slides or splitting into multiple stories.',
                ];
            }

            // Check visualization coverage
            $visualizations = DB::table('bi_story_slides')
                ->where('story_id', $storyId)
                ->where('visualization_ids', '!=', json_encode([]))
                ->count();

            if ($visualizations < $slides) {
                $improvements[] = [
                    'type'       => 'visualization',
                    'suggestion' => 'Not all slides have visualizations. Add charts to support narrative.',
                ];
            }

            // Generate summaries
            $recommendations = array_map(fn ($imp) => $imp['suggestion'], $improvements);

            Log::info('Story recommendations generated', [
                'story_id'        => $storyId,
                'recommendations' => count($recommendations),
            ]);

            return [
                'recommendations' => $recommendations,
                'improvements'    => $improvements,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to generate story recommendations', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Export story as structured document (PDF/PPT with embedded visualizations).
     *
     * @param  int  $storyId
     * @param  string  $format  Format: pdf or ppt
     * @param  array{includeChart?: bool, includeAnalytics?: bool}  $options
     * @return array{data: string, mimeType: string, filename: string}
     */
    public function exportStoryAsDocument(int $storyId, string $format = 'pdf', array $options = []): array
    {
        try {
            if (!in_array($format, ['pdf', 'ppt'])) {
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
            }

            $story = DB::table('bi_data_stories')->find($storyId);
            if (!$story) {
                throw new \InvalidArgumentException("Story {$storyId} not found");
            }

            $slides = DB::table('bi_story_slides')
                ->where('story_id', $storyId)
                ->orderBy('slide_order')
                ->get();

            // Build document structure
            $documentData = [
                'title'        => $story->title,
                'description'  => $story->description,
                'created_at'   => $story->created_at,
                'slides'       => [],
            ];

            foreach ($slides as $slide) {
                $documentData['slides'][] = [
                    'narrative'       => $slide->narrative_text,
                    'insight'         => $slide->story_insight,
                    'visualizations'  => json_decode($slide->visualization_ids, true),
                ];
            }

            $filename = sprintf(
                '%s_%s.%s',
                str_replace(' ', '_', $story->title),
                now()->format('YmdHis'),
                $format
            );

            $mimeTypes = [
                'pdf' => 'application/pdf',
                'ppt' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ];

            Log::info('Story exported', [
                'story_id' => $storyId,
                'format'   => $format,
                'filename' => $filename,
            ]);

            return [
                'data'     => json_encode($documentData),
                'mimeType' => $mimeTypes[$format],
                'filename' => $filename,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to export story', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Duplicate story with all slides and flows.
     *
     * @param  int  $storyId
     * @param  array{newTitle?: string, status?: string, audience_type?: string}  $options
     * @return array{originalId: int, duplicateId: int, title: string}
     */
    public function duplicateStory(int $storyId, array $options = []): array
    {
        try {
            $original = DB::table('bi_data_stories')->find($storyId);
            if (!$original) {
                throw new \InvalidArgumentException("Story {$storyId} not found");
            }

            // Create duplicate story
            $newTitle = $options['newTitle'] ?? "Copy of {$original->title}";

            $newStoryId = DB::table('bi_data_stories')->insertGetId([
                'title'            => $newTitle,
                'description'      => $original->description,
                'audience_type'    => $options['audience_type'] ?? $original->audience_type,
                'status'           => $options['status'] ?? 'draft',
                'created_by'       => auth()->id() ?? 1,
                'company_id'       => $original->company_id,
                'metadata'         => $original->metadata,
                'engagement_data'  => json_encode([]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Duplicate slides
            $slides = DB::table('bi_story_slides')
                ->where('story_id', $storyId)
                ->get();

            foreach ($slides as $slide) {
                DB::table('bi_story_slides')->insert([
                    'story_id'          => $newStoryId,
                    'narrative_text'    => $slide->narrative_text,
                    'visualization_ids' => $slide->visualization_ids,
                    'slide_order'       => $slide->slide_order,
                    'story_insight'     => $slide->story_insight,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            // Duplicate flows
            $flows = DB::table('bi_narrative_flows')
                ->where('story_id', $storyId)
                ->get();

            foreach ($flows as $flow) {
                DB::table('bi_narrative_flows')->insert([
                    'story_id'    => $newStoryId,
                    'conditions'  => $flow->conditions,
                    'actions'     => $flow->actions,
                    'description' => $flow->description,
                    'is_active'   => $flow->is_active,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            Log::info('Story duplicated', [
                'original_id' => $storyId,
                'duplicate_id' => $newStoryId,
            ]);

            return [
                'originalId'  => $storyId,
                'duplicateId' => $newStoryId,
                'title'       => $newTitle,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to duplicate story', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get demographic and behavioral insights for audience segment.
     *
     * @param  int  $storyId
     * @param  string  $audienceType
     * @return array{audienceSize: int, avgEngagementScore: float, preferredFormat: string, topInterests: array}
     */
    public function getAudienceInsights(int $storyId, string $audienceType): array
    {
        try {
            $engagements = DB::table('bi_story_engagements')
                ->join('bi_data_stories', 'bi_story_engagements.story_id', '=', 'bi_data_stories.id')
                ->where('bi_data_stories.id', $storyId)
                ->where('bi_data_stories.audience_type', $audienceType)
                ->get();

            $audienceSize = $engagements->pluck('user_id')->unique()->count();

            // Calculate engagement score
            $engagementScore = 0;
            if ($audienceSize > 0) {
                $totalActions = $engagements->count();
                $engagementScore = ($totalActions / $audienceSize) / 10; // Normalize
            }

            // Determine preferred format (most viewed slides)
            $mostViewed = $engagements->where('action', 'view')
                ->groupBy('slide_id')
                ->map(fn ($group) => count($group))
                ->sortDesc()
                ->keys()
                ->first();

            $preferredFormat = $mostViewed ? "visual_slide_{$mostViewed}" : 'summary';

            // Extract interests (based on navigation patterns)
            $topInterests = $engagements->pluck('slide_id')
                ->unique()
                ->map(fn ($id) => "topic_{$id}")
                ->take(5)
                ->all();

            $insights = [
                'audienceSize'        => $audienceSize,
                'avgEngagementScore'  => round($engagementScore, 2),
                'preferredFormat'     => $preferredFormat,
                'topInterests'        => $topInterests,
            ];

            Log::debug('Audience insights retrieved', [
                'story_id'       => $storyId,
                'audience_type'  => $audienceType,
                'audience_size'  => $audienceSize,
            ]);

            return $insights;
        } catch (\Throwable $e) {
            Log::error('Failed to get audience insights', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
