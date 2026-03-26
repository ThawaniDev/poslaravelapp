<?php

return [
    // ═══════════════════════════════════════════════════════════
    //  Navigation & Resource Labels
    // ═══════════════════════════════════════════════════════════
    'nav_tickets'          => 'Support Tickets',
    'nav_canned_responses' => 'Canned Responses',
    'nav_knowledge_base'   => 'Knowledge Base',
    'ticket'               => 'Support Ticket',
    'tickets'              => 'Support Tickets',
    'canned_response'      => 'Canned Response',
    'canned_responses'     => 'Canned Responses',
    'kb_article'           => 'KB Article',
    'kb_articles'          => 'KB Articles',
    'draft_articles'       => 'Draft articles',

    // ═══════════════════════════════════════════════════════════
    //  Ticket Status
    // ═══════════════════════════════════════════════════════════
    'status_open'        => 'Open',
    'status_in_progress' => 'In Progress',
    'status_resolved'    => 'Resolved',
    'status_closed'      => 'Closed',

    // ═══════════════════════════════════════════════════════════
    //  Ticket Priority
    // ═══════════════════════════════════════════════════════════
    'priority_low'      => 'Low',
    'priority_medium'   => 'Medium',
    'priority_high'     => 'High',
    'priority_critical' => 'Critical',

    // ═══════════════════════════════════════════════════════════
    //  Ticket Category
    // ═══════════════════════════════════════════════════════════
    'category_billing'         => 'Billing',
    'category_technical'       => 'Technical',
    'category_zatca'           => 'ZATCA',
    'category_feature_request' => 'Feature Request',
    'category_general'         => 'General',
    'category_hardware'        => 'Hardware',

    // ═══════════════════════════════════════════════════════════
    //  Sender Type
    // ═══════════════════════════════════════════════════════════
    'sender_provider' => 'Provider',
    'sender_admin'    => 'Admin',

    // ═══════════════════════════════════════════════════════════
    //  Knowledge Base Category
    // ═══════════════════════════════════════════════════════════
    'kb_cat_getting_started'  => 'Getting Started',
    'kb_cat_pos_usage'        => 'POS Usage',
    'kb_cat_inventory'        => 'Inventory',
    'kb_cat_delivery'         => 'Delivery',
    'kb_cat_billing'          => 'Billing',
    'kb_cat_troubleshooting'  => 'Troubleshooting',

    // ═══════════════════════════════════════════════════════════
    //  Form / Table Fields
    // ═══════════════════════════════════════════════════════════
    'ticket_number'      => 'Ticket #',
    'subject'            => 'Subject',
    'description'        => 'Description',
    'category'           => 'Category',
    'priority'           => 'Priority',
    'status'             => 'Status',
    'organization'       => 'Organization',
    'store'              => 'Store',
    'assigned_to'        => 'Assigned To',
    'agent'              => 'Agent',
    'sla_deadline'       => 'SLA Deadline',
    'first_response_at'  => 'First Response',
    'resolved_at'        => 'Resolved At',
    'closed_at'          => 'Closed At',
    'created_at'         => 'Created At',
    'updated_at'         => 'Updated At',
    'messages_count'     => 'Messages',
    'sla_status'         => 'SLA Status',
    'title'              => 'Title',
    'title_en'           => 'Title (EN)',
    'title_ar'           => 'Title (AR)',
    'body_en'            => 'Body (EN)',
    'body_ar'            => 'Body (AR)',
    'shortcut'           => 'Shortcut',
    'shortcut_help'      => 'Type / followed by the shortcut to quickly insert this response.',
    'is_active'          => 'Active',
    'is_published'       => 'Published',
    'slug'               => 'Slug',
    'slug_help'          => 'URL-friendly identifier. Auto-generated from title.',
    'sort_order'         => 'Sort Order',
    'created_by'         => 'Created By',

    // ═══════════════════════════════════════════════════════════
    //  Form Sections
    // ═══════════════════════════════════════════════════════════
    'ticket_details'      => 'Ticket Details',
    'ticket_description'  => 'Description',
    'assignment'          => 'Assignment',
    'response_details'    => 'Response Details',
    'response_body'       => 'Response Content',
    'article_details'     => 'Article Details',
    'article_body'        => 'Article Content',

    // ═══════════════════════════════════════════════════════════
    //  Infolist Sections
    // ═══════════════════════════════════════════════════════════
    'ticket_information'   => 'Ticket Information',
    'conversation'         => 'Conversation',
    'organization_info'    => 'Organization Info',
    'sla_tracking'         => 'SLA Tracking',
    'sla_met'              => 'Met',
    'sla_breached'         => 'Breached',
    'sla_on_track'         => 'On Track',
    'sla_none'             => 'Not Set',
    'internal_note'        => 'Internal Note',

    // ═══════════════════════════════════════════════════════════
    //  Actions
    // ═══════════════════════════════════════════════════════════
    'assign'              => 'Assign',
    'assign_agent'        => 'Assign Agent',
    'resolve'             => 'Resolve',
    'resolve_confirm'     => 'Mark this ticket as resolved?',
    'close_ticket'        => 'Close',
    'close_confirm'       => 'Close this ticket? This cannot be undone.',
    'reply'               => 'Reply',
    'reply_message'       => 'Your reply',
    'is_internal'         => 'Internal Note',
    'escalate'            => 'Escalate',
    'escalate_confirm'    => 'Set priority to critical and add internal note?',
    'change_priority'     => 'Change Priority',
    'new_priority'        => 'New Priority',
    'activate'            => 'Activate',
    'deactivate'          => 'Deactivate',
    'activated'           => 'Activated successfully.',
    'deactivated'         => 'Deactivated successfully.',
    'publish'             => 'Publish',
    'unpublish'           => 'Unpublish',

    // ═══════════════════════════════════════════════════════════
    //  Notifications
    // ═══════════════════════════════════════════════════════════
    'reply_sent'          => 'Reply sent successfully.',
    'ticket_escalated'    => 'Ticket escalated to critical.',
    'ticket_resolved'     => 'Ticket resolved.',
    'ticket_closed'       => 'Ticket closed.',
    'ticket_assigned'     => 'Ticket assigned successfully.',
    'tickets_assigned'    => 'Tickets assigned successfully.',
    'tickets_closed'      => 'Tickets closed successfully.',
    'priority_changed'    => 'Priority changed successfully.',
    'status_changed'      => 'Status changed successfully.',
    'ticket_updated'      => 'Ticket updated successfully.',

    // ═══════════════════════════════════════════════════════════
    //  Stats / Analytics
    // ═══════════════════════════════════════════════════════════
    'stat_total'           => 'Total Tickets',
    'stat_all_tickets'     => 'All-time ticket count',
    'stat_open'            => 'Open',
    'stat_awaiting_response' => 'Awaiting response',
    'stat_in_progress'     => 'In Progress',
    'stat_being_handled'   => 'Being handled',
    'stat_resolved_today'  => 'Resolved Today',
    'stat_closed_today'    => 'Closed today',
    'stat_sla_breached'    => 'SLA Breached',
    'stat_past_deadline'   => 'Past SLA deadline',
    'stat_critical'        => 'Critical',
    'stat_critical_tickets' => 'Critical open tickets',
    'stat_unassigned'      => 'Unassigned',
    'stat_needs_assignment' => 'Needs assignment',

    'chart_ticket_volume'  => 'Ticket Volume (30 Days)',
    'chart_created'        => 'Created',
    'chart_resolved'       => 'Resolved',
    'chart_by_category'    => 'Tickets by Category',

    // ═══════════════════════════════════════════════════════════
    //  API Messages
    // ═══════════════════════════════════════════════════════════
    'tickets_retrieved'          => 'Support tickets retrieved.',
    'ticket_retrieved'           => 'Support ticket retrieved.',
    'ticket_not_found'           => 'Ticket not found.',
    'ticket_created'             => 'Support ticket created.',
    'message_sent'               => 'Message sent.',
    'messages_retrieved'         => 'Messages retrieved.',
    'stats_retrieved'            => 'Support stats retrieved.',
    'canned_responses_retrieved' => 'Canned responses retrieved.',
    'canned_response_created'    => 'Canned response created.',
    'canned_response_retrieved'  => 'Canned response retrieved.',
    'canned_response_not_found'  => 'Canned response not found.',
    'canned_response_updated'    => 'Canned response updated.',
    'canned_response_deleted'    => 'Canned response deleted.',
    'canned_response_toggled'    => 'Canned response toggled.',
    'kb_articles_retrieved'      => 'Knowledge base articles retrieved.',
    'kb_article_retrieved'       => 'Knowledge base article retrieved.',
    'kb_article_not_found'       => 'Knowledge base article not found.',
    'kb_article_created'         => 'Knowledge base article created.',
    'kb_article_updated'         => 'Knowledge base article updated.',
    'kb_article_deleted'         => 'Knowledge base article deleted.',
];
