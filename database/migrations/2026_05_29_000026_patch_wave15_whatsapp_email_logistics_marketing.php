<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── wa_conversations: make contact_id nullable ──
        if (Schema::hasTable('wa_conversations') && Schema::hasColumn('wa_conversations', 'contact_id')) {
            Schema::table('wa_conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id')->nullable()->change();
            });
        }

        // ── WhatsApp tables ──────────────────────────────────────────────
        $this->patch('wa_campaign_recipients', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'campaign_id'))       $t->unsignedBigInteger('campaign_id')->nullable();
            if (!Schema::hasColumn($table, 'phone'))             $t->string('phone', 30)->nullable();
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'status'))            $t->string('status', 30)->default('pending');
            if (!Schema::hasColumn($table, 'sent_at'))           $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'error_message'))     $t->text('error_message')->nullable();
            if (!Schema::hasColumn($table, 'wa_message_id'))     $t->string('wa_message_id')->nullable();
        });

        $this->patch('wa_messages', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'conversation_id'))    $t->unsignedBigInteger('conversation_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 50)->default('text');
            if (!Schema::hasColumn($table, 'media'))              $t->json('media')->nullable();
            if (!Schema::hasColumn($table, 'template_name'))      $t->string('template_name')->nullable();
            if (!Schema::hasColumn($table, 'template_params'))    $t->json('template_params')->nullable();
            if (!Schema::hasColumn($table, 'is_ai_generated'))    $t->boolean('is_ai_generated')->default(false);
            if (!Schema::hasColumn($table, 'delivered_at'))       $t->timestamp('delivered_at')->nullable();
            if (!Schema::hasColumn($table, 'read_at'))            $t->timestamp('read_at')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))            $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'wa_contact_id'))      $t->unsignedBigInteger('wa_contact_id')->nullable();
        });

        $this->patch('wa_broadcast_campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'template_name'))    $t->string('template_name')->nullable();
            if (!Schema::hasColumn($table, 'template_params'))  $t->json('template_params')->nullable();
            if (!Schema::hasColumn($table, 'scheduled_at'))     $t->timestamp('scheduled_at')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))          $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))       $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('whatsapp_optins', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'channel'))          $t->string('channel', 50)->default('whatsapp');
            if (!Schema::hasColumn($table, 'opted_in_at'))      $t->timestamp('opted_in_at')->nullable();
            if (!Schema::hasColumn($table, 'opted_out_at'))     $t->timestamp('opted_out_at')->nullable();
        });

        $this->patch('wa_chatbot_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'context'))               $t->json('context')->nullable();
            if (!Schema::hasColumn($table, 'phone_number'))          $t->string('phone_number')->nullable();
            if (!Schema::hasColumn($table, 'contact_name'))          $t->string('contact_name')->nullable();
            if (!Schema::hasColumn($table, 'current_intent'))        $t->string('current_intent')->nullable();
            if (!Schema::hasColumn($table, 'message_count'))         $t->integer('message_count')->default(0);
            if (!Schema::hasColumn($table, 'last_message_at'))       $t->timestamp('last_message_at')->nullable();
            if (!Schema::hasColumn($table, 'closed_at'))             $t->timestamp('closed_at')->nullable();
            if (!Schema::hasColumn($table, 'assigned_to'))           $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'crm_contact_id'))        $t->unsignedBigInteger('crm_contact_id')->nullable();
            if (!Schema::hasColumn($table, 'helpdesk_ticket_id'))    $t->unsignedBigInteger('helpdesk_ticket_id')->nullable();
        });

        $this->patch('wa_chatbot_messages', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'session_id'))     $t->unsignedBigInteger('session_id')->nullable();
            if (!Schema::hasColumn($table, 'direction'))      $t->string('direction', 20)->default('inbound');
            if (!Schema::hasColumn($table, 'content'))        $t->text('content')->nullable();
            if (!Schema::hasColumn($table, 'intent'))         $t->string('intent')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))        $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'confidence'))     $t->decimal('confidence', 5, 4)->nullable();
            if (!Schema::hasColumn($table, 'metadata'))       $t->json('metadata')->nullable();
            if (!Schema::hasColumn($table, 'delivered_at'))   $t->timestamp('delivered_at')->nullable();
            if (!Schema::hasColumn($table, 'read_at'))        $t->timestamp('read_at')->nullable();
        });

        $this->patch('wa_chatbot_intents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'response_template'))  $t->text('response_template')->nullable();
            if (!Schema::hasColumn($table, 'display_name'))       $t->string('display_name')->nullable();
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'patterns'))           $t->json('patterns')->nullable();
            if (!Schema::hasColumn($table, 'action'))             $t->string('action', 50)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'priority'))           $t->integer('priority')->default(10);
        });

        $this->patch('wa_interactive_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'type'))          $t->string('type', 50)->default('button');
            if (!Schema::hasColumn($table, 'body_text'))     $t->text('body_text')->nullable();
            if (!Schema::hasColumn($table, 'buttons'))       $t->json('buttons')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))    $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'header_type'))   $t->string('header_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'language'))      $t->string('language', 10)->default('en');
            if (!Schema::hasColumn($table, 'status'))        $t->string('status', 30)->default('draft');
        });

        $this->patch('wa_catalog_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))    $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'price'))         $t->decimal('price', 12, 2)->nullable();
            if (!Schema::hasColumn($table, 'currency'))      $t->string('currency', 10)->default('EUR');
            if (!Schema::hasColumn($table, 'availability'))  $t->string('availability', 30)->default('in_stock');
            if (!Schema::hasColumn($table, 'retailer_id'))   $t->string('retailer_id')->nullable();
            if (!Schema::hasColumn($table, 'synced_at'))     $t->timestamp('synced_at')->nullable();
        });

        $this->patch('wa_agent_stats_daily', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))                       $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'date'))                          $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'conversations_handled'))         $t->integer('conversations_handled')->default(0);
            if (!Schema::hasColumn($table, 'messages_sent'))                 $t->integer('messages_sent')->default(0);
            if (!Schema::hasColumn($table, 'messages_received'))             $t->integer('messages_received')->default(0);
            if (!Schema::hasColumn($table, 'avg_response_time_seconds'))     $t->integer('avg_response_time_seconds')->nullable();
            if (!Schema::hasColumn($table, 'first_response_time_seconds'))   $t->integer('first_response_time_seconds')->nullable();
            if (!Schema::hasColumn($table, 'resolution_time_seconds'))       $t->integer('resolution_time_seconds')->nullable();
            if (!Schema::hasColumn($table, 'conversations_resolved'))        $t->integer('conversations_resolved')->default(0);
            if (!Schema::hasColumn($table, 'customer_satisfaction_score'))   $t->decimal('customer_satisfaction_score', 4, 2)->nullable();
        });

        $this->patch('whatsapp_payments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'contact_id'))         $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'conversation_id'))    $t->unsignedBigInteger('conversation_id')->nullable();
            if (!Schema::hasColumn($table, 'currency'))           $t->string('currency', 10)->default('EUR');
            if (!Schema::hasColumn($table, 'meta_payment_id'))    $t->string('meta_payment_id')->nullable();
            if (!Schema::hasColumn($table, 'payment_link'))       $t->string('payment_link')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))            $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 30)->default('pending');
        });

        $this->patch('whatsapp_payment_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'merchant_id'))      $t->string('merchant_id')->nullable();
            if (!Schema::hasColumn($table, 'display_name'))     $t->string('display_name')->nullable();
            if (!Schema::hasColumn($table, 'currency'))         $t->string('currency', 10)->default('EUR');
            if (!Schema::hasColumn($table, 'webhook_secret'))   $t->string('webhook_secret')->nullable();
            if (!Schema::hasColumn($table, 'active'))           $t->boolean('active')->default(true);
        });

        $this->patch('whatsapp_broadcasts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'recipient_count'))   $t->integer('recipient_count')->default(0);
            if (!Schema::hasColumn($table, 'delivered_count'))   $t->integer('delivered_count')->default(0);
            if (!Schema::hasColumn($table, 'read_count'))        $t->integer('read_count')->default(0);
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'template_id'))       $t->unsignedBigInteger('template_id')->nullable();
            if (!Schema::hasColumn($table, 'segment'))           $t->json('segment')->nullable();
            if (!Schema::hasColumn($table, 'scheduled_at'))      $t->timestamp('scheduled_at')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))           $t->timestamp('sent_at')->nullable();
        });

        // ── Email tables ─────────────────────────────────────────────────
        $this->patch('email_campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by'))          $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))          $t->softDeletes();
            if (!Schema::hasColumn($table, 'type'))                $t->string('type', 50)->default('regular');
            if (!Schema::hasColumn($table, 'list_id'))             $t->unsignedBigInteger('list_id')->nullable();
            if (!Schema::hasColumn($table, 'total_recipients'))    $t->integer('total_recipients')->default(0);
            if (!Schema::hasColumn($table, 'total_sent'))          $t->integer('total_sent')->default(0);
            if (!Schema::hasColumn($table, 'total_opens'))         $t->integer('total_opens')->default(0);
            if (!Schema::hasColumn($table, 'total_clicks'))        $t->integer('total_clicks')->default(0);
        });

        $this->patch('email_lists', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'slug'))              $t->string('slug')->nullable();
            if (!Schema::hasColumn($table, 'subscriber_count'))  $t->integer('subscriber_count')->default(0);
            if (!Schema::hasColumn($table, 'description'))       $t->text('description')->nullable();
        });

        $this->patch('email_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'variables'))    $t->json('variables')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))   $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('email_flow_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 50)->nullable();
            if (!Schema::hasColumn($table, 'config'))       $t->json('config')->nullable();
            if (!Schema::hasColumn($table, 'step_order'))   $t->integer('step_order')->default(0);
            if (!Schema::hasColumn($table, 'flow_id'))      $t->unsignedBigInteger('flow_id')->nullable();
        });

        $this->patch('email_journeys', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'trigger'))      $t->string('trigger', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))       $t->string('status', 30)->default('draft');
        });

        $this->patch('email_domain_authentications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'dmarc_policy'))     $t->string('dmarc_policy', 20)->default('none');
            if (!Schema::hasColumn($table, 'dkim_selector'))    $t->string('dkim_selector')->nullable();
            if (!Schema::hasColumn($table, 'dkim_public_key'))  $t->text('dkim_public_key')->nullable();
            if (!Schema::hasColumn($table, 'dkim_private_key')) $t->text('dkim_private_key')->nullable();
            if (!Schema::hasColumn($table, 'spf_record'))       $t->text('spf_record')->nullable();
            if (!Schema::hasColumn($table, 'dmarc_record'))     $t->text('dmarc_record')->nullable();
            if (!Schema::hasColumn($table, 'verified_spf'))     $t->boolean('verified_spf')->default(false);
            if (!Schema::hasColumn($table, 'verified_dkim'))    $t->boolean('verified_dkim')->default(false);
            if (!Schema::hasColumn($table, 'verified_dmarc'))   $t->boolean('verified_dmarc')->default(false);
            if (!Schema::hasColumn($table, 'last_checked_at'))  $t->timestamp('last_checked_at')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
        });

        $this->patch('email_segment_contacts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'segment_id'))   $t->unsignedBigInteger('segment_id')->nullable();
            if (!Schema::hasColumn($table, 'contact_id'))   $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'added_at'))     $t->timestamp('added_at')->nullable();
        });

        $this->patch('email_smtp_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        $this->patch('email_flow_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        $this->patch('email_journey_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'journey_id'))   $t->unsignedBigInteger('journey_id')->nullable();
            if (!Schema::hasColumn($table, 'order'))        $t->integer('order')->default(0);
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 50)->nullable();
            if (!Schema::hasColumn($table, 'config'))       $t->json('config')->nullable();
        });

        $this->patch('email_flow_enrollments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'flow_id'))          $t->unsignedBigInteger('flow_id')->nullable();
            if (!Schema::hasColumn($table, 'contact_email'))    $t->string('contact_email')->nullable();
            if (!Schema::hasColumn($table, 'contact_name'))     $t->string('contact_name')->nullable();
            if (!Schema::hasColumn($table, 'current_step'))     $t->integer('current_step')->default(0);
            if (!Schema::hasColumn($table, 'enrolled_at'))      $t->timestamp('enrolled_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))     $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'next_action_at'))   $t->timestamp('next_action_at')->nullable();
            if (!Schema::hasColumn($table, 'metadata'))         $t->json('metadata')->nullable();
        });

        $this->patch('email_template_blocks', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'template_id'))   $t->unsignedBigInteger('template_id')->nullable();
            if (!Schema::hasColumn($table, 'block_order'))   $t->integer('block_order')->default(0);
            if (!Schema::hasColumn($table, 'block_type'))    $t->string('block_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'config'))        $t->json('config')->nullable();
        });

        $this->patch('email_segments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_count_cache'))   $t->integer('contact_count_cache')->default(0);
            if (!Schema::hasColumn($table, 'last_computed_at'))      $t->timestamp('last_computed_at')->nullable();
        });

        // ── Logistics tables ─────────────────────────────────────────────
        $this->patch('logistics_locations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'capacity_units'))    $t->decimal('capacity_units', 12, 2)->nullable();
            if (!Schema::hasColumn($table, 'occupied_units'))    $t->decimal('occupied_units', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'max_weight_kg'))     $t->decimal('max_weight_kg', 10, 2)->nullable();
            if (!Schema::hasColumn($table, 'temperature_class')) $t->string('temperature_class', 30)->nullable();
        });

        $this->patch('logistics_customs_declarations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'country_export'))        $t->string('country_export', 5)->nullable();
            if (!Schema::hasColumn($table, 'country_import'))        $t->string('country_import', 5)->nullable();
            if (!Schema::hasColumn($table, 'total_declared_value'))  $t->decimal('total_declared_value', 14, 2)->nullable();
            if (!Schema::hasColumn($table, 'total_duties'))          $t->decimal('total_duties', 12, 2)->nullable();
            if (!Schema::hasColumn($table, 'total_taxes'))           $t->decimal('total_taxes', 12, 2)->nullable();
            if (!Schema::hasColumn($table, 'customs_broker'))        $t->string('customs_broker')->nullable();
            if (!Schema::hasColumn($table, 'mrn_number'))            $t->string('mrn_number')->nullable();
            if (!Schema::hasColumn($table, 'submitted_at'))          $t->timestamp('submitted_at')->nullable();
            if (!Schema::hasColumn($table, 'cleared_at'))            $t->timestamp('cleared_at')->nullable();
            if (!Schema::hasColumn($table, 'rejection_reason'))      $t->text('rejection_reason')->nullable();
            if (!Schema::hasColumn($table, 'notes'))                 $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))            $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'declared_value'))        $t->decimal('declared_value', 14, 2)->nullable();
            if (!Schema::hasColumn($table, 'hs_code'))               $t->string('hs_code', 30)->nullable();
            if (!Schema::hasColumn($table, 'item_description'))      $t->text('item_description')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))              $t->decimal('quantity', 12, 3)->nullable();
            if (!Schema::hasColumn($table, 'country_of_origin'))     $t->string('country_of_origin', 5)->nullable();
            if (!Schema::hasColumn($table, 'declaration_number'))    $t->string('declaration_number')->nullable()->unique();
        });

        $this->patch('logistics_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'origin_location_id'))       $t->unsignedBigInteger('origin_location_id')->nullable();
            if (!Schema::hasColumn($table, 'destination_location_id'))  $t->unsignedBigInteger('destination_location_id')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))               $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'carrier_rate_id'))          $t->unsignedBigInteger('carrier_rate_id')->nullable();
            if (!Schema::hasColumn($table, 'route_id'))                 $t->unsignedBigInteger('route_id')->nullable();
            if (!Schema::hasColumn($table, 'volume_cbm'))               $t->decimal('volume_cbm', 10, 3)->nullable();
            if (!Schema::hasColumn($table, 'declared_value'))           $t->decimal('declared_value', 14, 2)->nullable();
            if (!Schema::hasColumn($table, 'estimated_delivery_at'))    $t->timestamp('estimated_delivery_at')->nullable();
            if (!Schema::hasColumn($table, 'special_instructions'))     $t->text('special_instructions')->nullable();
        });

        $this->patch('logistics_tracking_events', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipment_id'))      $t->unsignedBigInteger('shipment_id')->nullable();
            if (!Schema::hasColumn($table, 'event_type'))       $t->string('event_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'status_detail'))    $t->string('status_detail')->nullable();
            if (!Schema::hasColumn($table, 'location_name'))    $t->string('location_name')->nullable();
            if (!Schema::hasColumn($table, 'recorded_at'))      $t->timestamp('recorded_at')->nullable();
            if (!Schema::hasColumn($table, 'recorded_by'))      $t->unsignedBigInteger('recorded_by')->nullable();
        });

        $this->patch('logistics_delivery_stops', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'delivery_round_id'))   $t->unsignedBigInteger('delivery_round_id')->nullable();
            if (!Schema::hasColumn($table, 'shipment_id'))         $t->unsignedBigInteger('shipment_id')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))         $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'stop_order'))          $t->integer('stop_order')->default(0);
            if (!Schema::hasColumn($table, 'sequence'))            $t->integer('sequence')->nullable();
            if (!Schema::hasColumn($table, 'delivery_window'))     $t->string('delivery_window')->nullable();
            if (!Schema::hasColumn($table, 'address'))             $t->text('address')->nullable();
            if (!Schema::hasColumn($table, 'contact_name'))        $t->string('contact_name')->nullable();
            if (!Schema::hasColumn($table, 'notes'))               $t->text('notes')->nullable();
        });

        $this->patch('logistics_delivery_rounds', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'carrier_id'))          $t->unsignedBigInteger('carrier_id')->nullable();
            if (!Schema::hasColumn($table, 'driver_name'))         $t->string('driver_name')->nullable();
            if (!Schema::hasColumn($table, 'driver_phone'))        $t->string('driver_phone')->nullable();
            if (!Schema::hasColumn($table, 'vehicle_plate'))       $t->string('vehicle_plate')->nullable();
            if (!Schema::hasColumn($table, 'vehicle_code'))        $t->string('vehicle_code')->nullable();
            if (!Schema::hasColumn($table, 'vehicle_type'))        $t->string('vehicle_type')->nullable();
            if (!Schema::hasColumn($table, 'planned_date'))        $t->date('planned_date')->nullable();
            if (!Schema::hasColumn($table, 'route'))               $t->string('route')->nullable();
            if (!Schema::hasColumn($table, 'reference'))           $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))          $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('logistics_carrier_rates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))                $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'carrier_id'))          $t->unsignedBigInteger('carrier_id')->nullable();
            if (!Schema::hasColumn($table, 'mode'))                $t->string('mode', 30)->nullable();
            if (!Schema::hasColumn($table, 'origin_country'))      $t->string('origin_country', 5)->nullable();
            if (!Schema::hasColumn($table, 'destination_country')) $t->string('destination_country', 5)->nullable();
            if (!Schema::hasColumn($table, 'rate_type'))           $t->string('rate_type', 30)->nullable();
            if (!Schema::hasColumn($table, 'base_rate'))           $t->decimal('base_rate', 12, 2)->nullable();
            if (!Schema::hasColumn($table, 'fuel_surcharge_pct'))  $t->decimal('fuel_surcharge_pct', 6, 2)->nullable();
            if (!Schema::hasColumn($table, 'insurance_rate_pct'))  $t->decimal('insurance_rate_pct', 6, 2)->nullable();
            if (!Schema::hasColumn($table, 'min_charge'))          $t->decimal('min_charge', 12, 2)->nullable();
            if (!Schema::hasColumn($table, 'currency'))            $t->string('currency', 10)->default('USD');
            if (!Schema::hasColumn($table, 'transit_days'))        $t->integer('transit_days')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))           $t->boolean('is_active')->default(true);
        });

        // ── MarketingAutomation tables ───────────────────────────────────
        $this->patch('contact_scores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'score_type'))   $t->string('score_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'points'))       $t->integer('points')->default(0);
            if (!Schema::hasColumn($table, 'is_manual'))    $t->boolean('is_manual')->default(false);
            if (!Schema::hasColumn($table, 'reason'))       $t->text('reason')->nullable();
        });

        $this->patch('product_webhooks', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'events'))       $t->json('events')->nullable();
            if (!Schema::hasColumn($table, 'status'))       $t->string('status', 30)->default('active');
            if (!Schema::hasColumn($table, 'secret'))       $t->string('secret')->nullable();
        });

        $this->patch('campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'started_at'))   $t->timestamp('started_at')->nullable();
        });

        $this->patch('marketing_contacts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'opt_in_date'))           $t->date('opt_in_date')->nullable();
            if (!Schema::hasColumn($table, 'unsubscribe_date'))      $t->date('unsubscribe_date')->nullable();
            if (!Schema::hasColumn($table, 'privacy_policy_version')) $t->string('privacy_policy_version')->nullable();
        });

        $this->patch('contact_scores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))   $t->softDeletes();
        });

        $this->patch('segments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))   $t->softDeletes();
        });

        $this->patch('marketing_contacts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))   $t->softDeletes();
        });

        $this->patch('lead_activities', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))   $t->softDeletes();
        });

        $this->patch('webhook_events', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'webhook_id'))         $t->unsignedBigInteger('webhook_id')->nullable();
            if (!Schema::hasColumn($table, 'event_type'))         $t->string('event_type', 100)->nullable();
            if (!Schema::hasColumn($table, 'payload'))            $t->json('payload')->nullable();
            if (!Schema::hasColumn($table, 'retries'))            $t->integer('retries')->default(0);
            if (!Schema::hasColumn($table, 'last_attempted_at'))  $t->timestamp('last_attempted_at')->nullable();
        });

        $this->patch('contact_consent_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_id'))              $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'consent_type'))            $t->string('consent_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'privacy_policy_version'))  $t->string('privacy_policy_version')->nullable();
            if (!Schema::hasColumn($table, 'ip_address'))              $t->string('ip_address')->nullable();
            if (!Schema::hasColumn($table, 'user_agent'))              $t->text('user_agent')->nullable();
        });

        $this->patch('lead_activities', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_id'))   $t->unsignedBigInteger('contact_id')->nullable();
        });

        $this->patch('campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'ended_at'))     $t->timestamp('ended_at')->nullable();
        });

        $this->patch('campaign_executions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'campaign_id'))  $t->unsignedBigInteger('campaign_id')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))   $t->softDeletes();
        });

        // contact_segment pivot table
        if (false) { // 'contact_segment' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('contact_segment', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id');
                $table->unsignedBigInteger('segment_id');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // no-op
    }

    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }
};
