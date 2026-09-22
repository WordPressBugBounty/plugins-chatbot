export const appsMetadata = [
  {
    id: "wordpress",
    name: "WordPress",
    icon: "W",
    iconBg: "bg-blue-600",
    categories: [
      {
        id: "triggers",
        name: "Triggers",
        actions: [
          { id: "wp_user_register", name: "User register" },
          { id: "wp_user_login", name: "User login" },
          { id: "wp_user_logout", name: "User logout" },
          { id: "wp_post_publish", name: "Post / Page publish" },
          { id: "wp_post_update", name: "Post / Page update" },
          { id: "wp_comment_added", name: "Comment added" },
          { id: "wp_media_upload", name: "Media upload" },
        ]
      },
      {
        id: "user_management",
        name: "User Management",
        actions: [
          { id: "wp_create_user", name: "Create New User" },
          { id: "wp_update_user", name: "Update User" },
          { id: "wp_delete_user", name: "Delete User" },
        ]
      },
      {
        id: "user_retrieval",
        name: "User Retrieval",
        actions: [
          { id: "wp_get_all_users", name: "Get All Users" },
          { id: "wp_get_users_by_role", name: "Get All Users by Role" },
          { id: "wp_get_user_by_id", name: "Get User by Id" },
          { id: "wp_get_user_by_email", name: "Get User by Email" },
          { id: "wp_get_user_by_field", name: "Get User by Field" },
        ]
      },
      {
        id: "user_metadata",
        name: "User Metadata",
        actions: [
          { id: "wp_get_user_meta_all", name: "Get User Metadata (All)" },
          { id: "wp_get_user_meta_single", name: "Get User Metadata (Single)" },
          { id: "wp_update_user_meta", name: "Update User Metadata" },
        ]
      },
      {
        id: "role_management",
        name: "Role Management",
        actions: [
          { id: "wp_create_role", name: "Create Role" },
          { id: "wp_delete_role", name: "Delete Role" },
          { id: "wp_add_user_role", name: "Add User Role" },
          { id: "wp_remove_user_role", name: "Remove User Role" },
          { id: "wp_update_user_role", name: "Update User Role" },
          { id: "wp_get_all_roles", name: "Get All Roles" },
        ]
      },
      {
        id: "capabilities_management",
        name: "Capabilities Management",
        actions: [
          { id: "wp_get_all_caps", name: "Get All Capabilities" },
          { id: "wp_get_role_caps", name: "Get Role Capabilities" },
          { id: "wp_add_role_caps", name: "Add Role Capabilities" },
          { id: "wp_remove_role_caps", name: "Remove Role Capabilities" },
          { id: "wp_get_user_caps", name: "Get User Capabilities" },
          { id: "wp_add_user_caps", name: "Add User Capabilities" },
          { id: "wp_remove_user_caps", name: "Remove User Capabilities" },
        ]
      },
      {
        id: "post_management",
        name: "Post Management",
        actions: [
          {
            id: "wp_create_post",
            name: "Create New Post",
            fields: [
              { id: "post_title", name: "Post Title", type: "text", placeholder: "Enter post title" },
              { id: "post_content", name: "Post Content", type: "textarea", placeholder: "Enter post content (or use {response})" },
              {
                id: "post_status",
                name: "Post Status",
                type: "select",
                options: [
                  { id: "publish", name: "Publish" },
                  { id: "draft", name: "Draft" },
                  { id: "pending", name: "Pending" },
                  { id: "private", name: "Private" },
                ],
                defaultValue: "publish"
              },
              { id: "categories", name: "Categories / Topics", type: "text", placeholder: "Category IDs or names (comma separated)" }
            ]
          },
          {
            id: "wp_create_page",
            name: "Create New Page",
            fields: [
              { id: "post_title", name: "Page Title", type: "text", placeholder: "Enter page title" },
              { id: "post_content", name: "Page Content", type: "textarea", placeholder: "Enter page content (or use {response})" },
              {
                id: "post_status",
                name: "Page Status",
                type: "select",
                options: [
                  { id: "publish", name: "Publish" },
                  { id: "draft", name: "Draft" },
                  { id: "pending", name: "Pending" },
                  { id: "private", name: "Private" },
                ],
                defaultValue: "publish"
              }
            ]
          },
          { id: "wp_update_post", name: "Update Post" },
          { id: "wp_update_post_status", name: "Update Post Status" },
          { id: "wp_delete_post", name: "Delete Post" },
          { id: "wp_get_posts_all", name: "Get Post (All)" },
          { id: "wp_get_post_single", name: "Get Post (Single)" },
          { id: "wp_get_posts_by_type", name: "Get Posts By Post Type" },
          { id: "wp_get_posts_by_meta", name: "Get Posts by Metadata" },
          { id: "wp_get_post_meta_all", name: "Get Post Metadata (All)" },
          { id: "wp_get_post_meta_single", name: "Get Post Metadata (Single)" },
          { id: "wp_get_post_permalink", name: "Get Post Permalink" },
          { id: "wp_get_post_content", name: "Get Post Content" },
          { id: "wp_get_post_excerpt", name: "Get Post Excerpt" },
          { id: "wp_get_post_status", name: "Get Post Status" },
          {
            id: "wp_ai_generated_post",
            name: "AI Generated Post",
            description: "Generate a WordPress post using AI based on your instructions.",
            fields: [
              {
                id: "ai_provider",
                name: "AI Provider",
                type: "select",
                options: [
                  { id: "openai", name: "OpenAI" },
                  { id: "gemini", name: "Gemini" },
                  { id: "claude", name: "Claude" },
                ],
                defaultValue: "openai"
              },
              { id: "api_key", name: "API Key", type: "text", placeholder: "Enter API Key" },
              {
                id: "model",
                name: "Model",
                type: "select",
                options: [
                  { id: "gpt-4o", name: "GPT-4o (OpenAI)" },
                  { id: "gpt-4o-mini", name: "GPT-4o Mini (OpenAI)" },
                  { id: "gpt-4-turbo", name: "GPT-4 Turbo (OpenAI)" },
                  { id: "gpt-3.5-turbo", name: "GPT-3.5 Turbo (OpenAI)" },
                  { id: "gemini-1.5-flash", name: "Gemini 1.5 Flash (Google)" },
                  { id: "gemini-1.5-pro", name: "Gemini 1.5 Pro (Google)" },
                  { id: "claude-3-5-sonnet-20240620", name: "Claude 3.5 Sonnet (Anthropic)" },
                  { id: "claude-3-haiku-20240307", name: "Claude 3 Haiku (Anthropic)" },
                ],
                defaultValue: "gpt-3.5-turbo"
              },
              { id: "prompt_instructions", name: "Instructions for AI", type: "textarea", placeholder: "e.g., Write a 500-word blog post about the history of coffee." },
              { id: "post_title", name: "Post Title", type: "text", placeholder: "Enter post title (Leave empty to let AI generate it)" },
              {
                id: "post_status",
                name: "Post Status",
                type: "select",
                options: [
                  { id: "publish", name: "Publish" },
                  { id: "draft", name: "Draft" },
                  { id: "pending", name: "Pending" },
                ],
                defaultValue: "publish"
              },
              { id: "categories", name: "Categories / Topics", type: "text", placeholder: "Category IDs or names (comma separated)" }
            ]
          },
          {
            id: "wp_ai_generate_social_posts",
            name: "Generate Social Media Posts (Facebook & Telegram)",
            description: "Generate optimized posts for Facebook and Telegram from content.",
            fields: [
              {
                id: "ai_provider",
                name: "AI Provider",
                type: "select",
                options: [
                  { id: "openai", name: "OpenAI" },
                  { id: "gemini", name: "Gemini" },
                  { id: "claude", name: "Claude" },
                ],
                defaultValue: "openai"
              },
              { id: "api_key", name: "API Key", type: "text", placeholder: "Enter API Key" },
              {
                id: "model",
                name: "Model",
                type: "select",
                options: [
                  { id: "gpt-4o", name: "GPT-4o (OpenAI)" },
                  { id: "gpt-4o-mini", name: "GPT-4o Mini (OpenAI)" },
                  { id: "gemini-1.5-flash", name: "Gemini 1.5 Flash (Google)" },
                  { id: "claude-3-5-sonnet-20240620", name: "Claude 3.5 Sonnet (Anthropic)" },
                ],
                defaultValue: "gpt-4o-mini"
              },
              { id: "source_content", name: "Source Content", type: "textarea", placeholder: "Map {post_content} or enter text" },
              { id: "additional_instructions", name: "Additional Instructions", type: "textarea", placeholder: "e.g., Use emojis, formal tone, etc." },
              { id: "generate_image", name: "Generate AI Image?", type: "checkbox", defaultValue: false },
              {
                id: "image_model",
                name: "Image Model",
                type: "select",
                options: [
                  { id: "dall-e-3", name: "DALL-E 3 (OpenAI)" },
                  { id: "dall-e-2", name: "DALL-E 2 (OpenAI)" },
                ],
                defaultValue: "dall-e-3"
              },
              {
                id: "image_size",
                name: "Image Size",
                type: "select",
                options: [
                  { id: "1024x1024", name: "1024x1024" },
                  { id: "1024x1792", name: "1024x1792 (Tall)" },
                  { id: "1792x1024", name: "1792x1024 (Wide)" },
                ],
                defaultValue: "1024x1024"
              },
              {
                id: "image_style",
                name: "Image Style",
                type: "select",
                options: [
                  { id: "vivid", name: "Vivid" },
                  { id: "natural", name: "Natural" },
                ],
                defaultValue: "vivid"
              },
            ]
          },
          {
            id: "wp_ai_generate_social_posts_pro",
            name: "Generate Social Media Posts (Insta, WhatsApp, LinkedIn)",
            description: "Generate optimized posts for Instagram, WhatsApp, and LinkedIn from content.",
            isPro: true,
            fields: [
              {
                id: "ai_provider",
                name: "AI Provider",
                type: "select",
                options: [
                  { id: "openai", name: "OpenAI" },
                  { id: "gemini", name: "Gemini" },
                  { id: "claude", name: "Claude" },
                ],
                defaultValue: "openai"
              },
              { id: "api_key", name: "API Key", type: "text", placeholder: "Enter API Key" },
              {
                id: "model",
                name: "Model",
                type: "select",
                options: [
                  { id: "gpt-4o", name: "GPT-4o (OpenAI)" },
                  { id: "gpt-4o-mini", name: "GPT-4o Mini (OpenAI)" },
                  { id: "gemini-1.5-flash", name: "Gemini 1.5 Flash (Google)" },
                  { id: "claude-3-5-sonnet-20240620", name: "Claude 3.5 Sonnet (Anthropic)" },
                ],
                defaultValue: "gpt-4o-mini"
              },
              { id: "source_content", name: "Source Content", type: "textarea", placeholder: "Map {post_content} or enter text" },
              { id: "additional_instructions", name: "Additional Instructions", type: "textarea", placeholder: "e.g., Use emojis, formal tone, etc." },
              { id: "generate_image", name: "Generate AI Image?", type: "checkbox", defaultValue: false },
              {
                id: "image_model",
                name: "Image Model",
                type: "select",
                options: [
                  { id: "dall-e-3", name: "DALL-E 3 (OpenAI)" },
                  { id: "dall-e-2", name: "DALL-E 2 (OpenAI)" },
                ],
                defaultValue: "dall-e-3"
              },
              {
                id: "image_size",
                name: "Image Size",
                type: "select",
                options: [
                  { id: "1024x1024", name: "1024x1024" },
                  { id: "1024x1792", name: "1024x1792 (Tall)" },
                  { id: "1792x1024", name: "1792x1024 (Wide)" },
                ],
                defaultValue: "1024x1024"
              },
              {
                id: "image_style",
                name: "Image Style",
                type: "select",
                options: [
                  { id: "vivid", name: "Vivid" },
                  { id: "natural", name: "Natural" },
                ],
                defaultValue: "vivid"
              },
            ]
          },
        ]
      },
      {
        id: "comment_management",
        name: "Comment Management",
        actions: [
          { id: "wp_get_comments_all", name: "Get Post Comments (All)" },
          { id: "wp_get_comments_single", name: "Get Post Comments (Single Post)" },
          { id: "wp_get_user_comments", name: "Get User Comments" },
          { id: "wp_get_user_comments_email", name: "Get User Comments (By Email)" },
          { id: "wp_get_comment_meta_all", name: "Get Comment Metadata (All)" },
          { id: "wp_get_comment_meta_single", name: "Get Comment Metadata (Single)" },
          {
            id: "wp_create_comment",
            name: "Create New Comment",
            fields: [
              {
                id: "post_id",
                name: "Post ID",
                type: "number",
                placeholder: "Enter Post ID (ID of the post to comment on)"
              },
              {
                id: "comment_content",
                name: "Comment Content",
                type: "textarea",
                placeholder: "Enter your comment here..."
              }
            ]
          },
          {
            id: "wp_reply_comment",
            name: "Reply To Comment",
            fields: [
              {
                id: "parent_id",
                name: "Parent Comment ID",
                type: "number",
                placeholder: "Enter Comment ID to reply to (leave empty to use trigger context)"
              },
              {
                id: "comment_content",
                name: "Reply Content",
                type: "textarea",
                placeholder: "Enter your reply content here..."
              }
            ]
          },
          {
            id: "wp_ai_reply_comment",
            name: "AI Comment Reply",
            description: "Automatically reply to a comment using AI.",
            fields: [
              {
                id: "ai_provider",
                name: "AI Provider",
                type: "select",
                options: [
                  { id: "openai", name: "OpenAI" },
                  { id: "gemini", name: "Gemini" },
                  { id: "claude", name: "Claude" },
                ],
                defaultValue: "openai"
              },
              { id: "api_key", name: "API Key", type: "text", placeholder: "Enter API Key" },
              {
                id: "model",
                name: "Model",
                type: "select",
                options: [
                  { id: "gpt-4o", name: "GPT-4o (OpenAI)" },
                  { id: "gpt-4o-mini", name: "GPT-4o Mini (OpenAI)" },
                  { id: "gpt-4-turbo", name: "GPT-4 Turbo (OpenAI)" },
                  { id: "gpt-3.5-turbo", name: "GPT-3.5 Turbo (OpenAI)" },
                  { id: "gemini-2.0-flash-exp", name: "Gemini 2.0 Flash exp (Google)" },
                  { id: "gemini-2.0-flash", name: "Gemini 2.0 Flash (Google)" },
                  { id: "gemini-1.5-pro", name: "Gemini 1.5 Pro (Google)" },
                  { id: "claude-3-5-sonnet-20240620", name: "Claude 3.5 Sonnet (Anthropic)" },
                  { id: "claude-3-haiku-20240307", name: "Claude 3 Haiku (Anthropic)" },
                ],
                defaultValue: "gpt-3.5-turbo"
              },
              { id: "prompt_instructions", name: "Instructions for AI", type: "textarea", placeholder: "e.g., Be helpful and polite. Answer any questions asked." },
              {
                id: "parent_id",
                name: "Parent Comment ID",
                type: "number",
                placeholder: "Leave empty to use trigger context"
              }
            ]
          },
          { id: "wp_delete_comment", name: "Delete Comment" },
        ]
      },
      {
        id: "post_type_management",
        name: "Post Type Management",
        actions: [
          { id: "wp_get_post_type_all", name: "Get Post Type (All)" },
          { id: "wp_get_post_type_single", name: "Get Post Type (Single Post)" },
          { id: "wp_register_post_type", name: "Register Post Type" },
          { id: "wp_unregister_post_type", name: "Unregister Post Type" },
          { id: "wp_add_post_type_features", name: "Add Post Type Features (Support)" },
        ]
      },
      {
        id: "post_tag_management",
        name: "Post Tag Management",
        actions: [
          { id: "wp_create_tag", name: "Create Post Tag" },
          { id: "wp_update_tag", name: "Update Post Tag" },
          { id: "wp_delete_tag", name: "Delete Post Tag" },
          { id: "wp_add_tags_to_post", name: "Add Tags to Post" },
          { id: "wp_remove_tags_from_post", name: "Remove Tags From Post" },
          { id: "wp_get_tags_all", name: "Get Post Tag (All)" },
          { id: "wp_get_tag_single", name: "Get Post Tag (Single)" },
        ]
      },
      {
        id: "media_management",
        name: "Media Management",
        actions: [
          { id: "wp_add_media", name: "Add New Image To Media Library" },
          { id: "wp_delete_media", name: "Delete Media From Media Library" },
          { id: "wp_rename_media", name: "Rename Media" },
          { id: "wp_get_media_all", name: "Get Media (All)" },
          { id: "wp_get_media_by_title", name: "Get Media (By Title)" },
          { id: "wp_get_media_by_id", name: "Get Media (By Id)" },
        ]
      },
      {
        id: "term_management",
        name: "Term Management",
        actions: [
          { id: "wp_get_terms_all", name: "Get Term (All)" },
          { id: "wp_get_term_single", name: "Get Term (Single)" },
          { id: "wp_get_terms_by_tax", name: "Get Term by Taxonomy" },
          { id: "wp_get_term_by_field", name: "Get Term by Field" },
          { id: "wp_create_term", name: "Create New Term" },
          { id: "wp_update_term", name: "Update Term" },
          { id: "wp_delete_term", name: "Delete Term" },
        ]
      },
      {
        id: "taxonomy_management",
        name: "Taxonomy Management",
        actions: [
          { id: "wp_register_tax", name: "Register Taxonomy" },
          { id: "wp_unregister_tax", name: "Unregister Taxonomy" },
          { id: "wp_get_tax_all", name: "Get Taxonomy (All)" },
          { id: "wp_get_tax_single", name: "Get Taxonomy (Single)" },
          { id: "wp_add_tax_to_post", name: "Add Taxonomy to Post" },
          { id: "wp_remove_tax_from_post", name: "Remove Taxonomy From Post" },
        ]
      },
      {
        id: "category_management",
        name: "Category Management",
        actions: [
          { id: "wp_create_cat", name: "Create Category" },
          { id: "wp_update_cat", name: "Update Category" },
          { id: "wp_delete_cat", name: "Delete Category" },
          { id: "wp_add_cat_to_post", name: "Add Category To Post" },
          { id: "wp_get_cats_all", name: "Get Category (All)" },
          { id: "wp_get_cat_single", name: "Get Category (Single)" },
        ]
      },
      {
        id: "product_tag_management",
        name: "Product Tag Management",
        actions: [
          { id: "wc_create_product_tag", name: "Create Product Tag" },
          { id: "wc_update_product_tag", name: "Update Product Tag" },
          { id: "wc_delete_product_tag", name: "Delete Product Tag" },
          { id: "wc_get_product_tags_all", name: "Get Product Tag (All)" },
          { id: "wc_get_product_tag_single", name: "Get Product Tag (Single)" },
        ]
      },
      {
        id: "product_category_management",
        name: "Product Category Management",
        actions: [
          { id: "wc_create_product_cat", name: "Create Product Category" },
          { id: "wc_update_product_cat", name: "Update Product Category" },
          { id: "wc_delete_product_cat", name: "Delete Product Category" },
          { id: "wc_get_product_cats_all", name: "Get Product Category (All)" },
          { id: "wc_get_product_cat_single", name: "Get Product Category (Single)" },
        ]
      },
      {
        id: "product_type_management",
        name: "Product Type Management",
        actions: [
          { id: "wc_create_product_type", name: "Create Product Type" },
          { id: "wc_update_product_type", name: "Update Product Type" },
          { id: "wc_delete_product_type", name: "Delete Product Type" },
          { id: "wc_get_product_types_all", name: "Get Product Type (All)" },
          { id: "wc_get_product_type_single", name: "Get Product Type (Single)" },
        ]
      },
      {
        id: "plugin_management",
        name: "Plugin Management",
        actions: [
          { id: "wp_check_plugin_status", name: "Check Plugin Activation Status" },
          { id: "wp_activate_plugin", name: "Activate Plugin" },
        ]
      },
    ]
  },
  {
    id: "woocommerce",
    name: "WooCommerce",
    isTrigger: true,
    icon: "WC",
    iconBg: "bg-purple-600",
    actions: [
      {
        id: "wc_order_created",
        name: "Order created",
        fields: [
          {
            id: "product_id",
            name: "Specific Product",
            type: "select",
            optionsUrl: "/products",
            placeholder: "Select a product (optional)"
          }
        ]
      },
      {
        id: "wc_order_status_change",
        name: "Order status change",
        fields: [
          {
            id: "new_status",
            name: "Only when status changes to",
            type: "select",
            options: [
              { id: "", name: "Any status" },
              { id: "completed", name: "Completed" },
              { id: "processing", name: "Processing" },
              { id: "on-hold", name: "On Hold" },
              { id: "refunded", name: "Refunded" },
              { id: "cancelled", name: "Cancelled" },
              { id: "failed", name: "Failed" },
            ],
            placeholder: "Any status (leave empty to trigger on any)"
          },
          {
            id: "product_id",
            name: "Specific Product",
            type: "select",
            optionsUrl: "/products",
            placeholder: "Select a product (optional)"
          }
        ]
      },
      {
        id: "wc_payment_completed",
        name: "Payment completed",
        fields: [
          {
            id: "product_id",
            name: "Specific Product",
            type: "select",
            optionsUrl: "/products",
            placeholder: "Select a product (optional)"
          }
        ]
      },
      { id: "wc_product_added", name: "Product added" },
    ]
  },
  {
    id: "wpforms",
    name: "WPForms",
    isTrigger: true,
    icon: "F",
    iconBg: "bg-orange-500",
    actions: [
      {
        id: "wpforms_submit",
        name: "Form submitted",
        fields: [
          {
            id: "form_id",
            name: "Specific Form",
            type: "select",
            optionsUrl: "/wpforms-forms",
            placeholder: "All forms (leave empty to trigger on any)"
          },
          {
            id: "specific_fields",
            name: "Map Specific Fields",
            type: "text",
            isPro: true,
            placeholder: "e.g., your-name, your-email (comma-separated field keys)"
          }
        ]
      },
    ]
  },
  {
    id: "cf7",
    name: "Contact Form 7",
    isTrigger: true,
    icon: "C7",
    iconBg: "bg-black",
    actions: [
      {
        id: "cf7_submit",
        name: "Form submitted",
        fields: [
          {
            id: "form_id",
            name: "Specific Form",
            type: "select",
            optionsUrl: "/cf7-forms",
            placeholder: "All forms (leave empty to trigger on any)"
          },
          {
            id: "specific_fields",
            name: "Map Specific Fields",
            type: "text",
            isPro: true,
            placeholder: "e.g., your-name, your-email (comma-separated field keys)"
          }
        ]
      },
    ]
  },
  {
    id: "mail",
    name: "Mail",
    icon: "M",
    iconBg: "bg-gray-700",
    actions: [
      {
        id: "send_email",
        name: "Send Email",
        description: "To send email through your website.Need to configure your site SMTP.",
        fields: [
          {
            id: "from_email",
            name: "From Email",
            type: "text",
            placeholder: "Email address that will be used to send the email."
          },
          {
            id: "from_name",
            name: "From Name",
            type: "text",
            placeholder: "Name that will be used to send the email."
          },
          {
            id: "to_email",
            name: "To",
            type: "text",
            placeholder: "Email address to which the email will be sent. Multiple emails separated by commas."
          },
          {
            id: "cc_email",
            name: "CC",
            type: "text",
            placeholder: "Email address to which a copy will be sent. Multiple emails separated by commas."
          },
          {
            id: "bcc_email",
            name: "BCC",
            type: "text",
            placeholder: "Email address to which a blind copy will be sent. Multiple emails separated by commas."
          },
          {
            id: "reply_to",
            name: "Reply To",
            type: "text",
            placeholder: "Email address to which replies will be sent."
          },
          {
            id: "subject",
            name: "Subject",
            type: "text",
            placeholder: "Subject of the email."
          },
          {
            id: "body",
            name: "Body",
            type: "textarea",
            placeholder: "Content of the email. You can map an HTML field here."
          },
          {
            id: "template_id",
            name: "Email Template (Optional)",
            type: "select",
            optionsUrl: "/email-templates",
            placeholder: "Select an email template (Overrides body if selected)"
          },
          {
            id: "is_html",
            name: "Is this email in HTML format?",
            type: "checkbox",
            defaultValue: true
          }
        ]
      }
    ]
  },
  {
    id: "webhook",
    name: "Webhook",
    isTrigger: true,
    icon: "W",
    iconBg: "bg-violet-600",
    categories: [
      {
        id: "triggers",
        name: "Triggers",
        actions: [
          {
            id: "incoming_webhook",
            name: "Incoming Webhook",
            description: "Starts workflow when an HTTP request is received.",
          }
        ]
      },
      {
        id: "actions",
        name: "Actions",
        actions: [
          {
            id: "outgoing_webhook",
            name: "Outgoing Webhook",
            description: "Send data to another URL.",
            fields: [
              {
                id: "url",
                name: "Request URL",
                type: "text",
                placeholder: "https://example.com/api"
              },
              {
                id: "method",
                name: "Method",
                type: "select",
                options: [
                  { id: "GET", name: "GET" },
                  { id: "POST", name: "POST" },
                  { id: "PUT", name: "PUT" },
                  { id: "DELETE", name: "DELETE" }
                ],
                defaultValue: "POST"
              },
              {
                id: "headers",
                name: "Headers",
                type: "keyvalue",
                placeholder: "Add Header"
              },
              {
                id: "params",
                name: "Query Params",
                type: "keyvalue",
                placeholder: "Add Param"
              },
              {
                id: "body",
                name: "Body",
                type: "textarea",
                placeholder: "JSON Body (e.g., {\"key\": \"{{token}}\"})"
              }
            ]
          }
        ]
      }
    ]
  },
  {
    id: "facebook",
    name: "Facebook",
    icon: "fb",
    iconBg: "bg-blue-600",
    credentialConfig: {
      fields: [
        { id: "page_access_token", name: "Page Access Token", type: "text", placeholder: "Enter long-lived Page Access Token" }
      ]
    },
    categories: [
      {
        id: "triggers",
        name: "Triggers",
        actions: [
          {
            id: "facebook_lead_ads",
            name: "Facebook Lead Ads",
            description: "Starts workflow when a new lead is submitted on Facebook.",
            fields: [
              {
                id: "hub_verify_token",
                name: "Verify Token",
                type: "text",
                placeholder: "Custom verification token for webhook"
              },
              {
                id: "page_access_token",
                name: "Page Access Token",
                type: "text",
                placeholder: "Enter Facebook Page Access Token (Legacy)"
              }
            ]
          }
        ]
      },
      {
        id: "actions",
        name: "Actions",
        actions: [
          {
            id: "fb_create_post",
            name: "Create a Page Post",
            description: "Publish a new post to your Facebook Page directly.",
            fields: [
              { id: "credential", name: "Credential", type: "credential" },
              { id: "page_id", name: "Page ID", type: "text", placeholder: "Enter your Facebook Page ID" },
              { id: "message", name: "Message", type: "textarea", placeholder: "What's on your mind? Use {post_content} to send post body, {post_title} for title, {post_url} for link." },
              { id: "link", name: "Link URL (Optional)", type: "text", placeholder: "https://example.com" }
            ]
          }
        ]
      }
    ]
  },
  {
    id: "api",
    name: "API",
    icon: "A",
    iconBg: "bg-slate-700",
    actions: [
      {
        id: "api_request",
        name: "API Request",
        description: "Make an HTTP request to an external API.",
        fields: [
          {
            id: "url",
            name: "API URL",
            type: "text",
            placeholder: "https://api.example.com/data"
          },
          {
            id: "method",
            name: "Method",
            type: "select",
            options: [
              { id: "GET", name: "GET" },
              { id: "POST", name: "POST" },
              { id: "PUT", name: "PUT" },
              { id: "DELETE", name: "DELETE" }
            ],
            defaultValue: "GET"
          },
          {
            id: "headers",
            name: "Headers",
            type: "keyvalue",
            placeholder: "Add Header"
          },
          {
            id: "params",
            name: "Query Params",
            type: "keyvalue",
            placeholder: "Add Param"
          },
          {
            id: "body",
            name: "Body",
            type: "textarea",
            placeholder: "Request Body (JSON, text, etc.)"
          }
        ]
      }
    ]
  },
  {
    id: "mailboxlayer",
    name: "MailboxLayer",
    icon: "ML",
    iconBg: "bg-blue-600",
    actions: [
      {
        id: "validate_email",
        name: "Validate Email Address",
        description: "Check if an email address exists and is deliverable using MailboxLayer.",
        fields: [
          {
            id: "api_key",
            name: "API Access Key",
            type: "text",
            placeholder: "Enter MailboxLayer API Access Key"
          },
          {
            id: "email",
            name: "Email to Validate",
            type: "text",
            placeholder: "Select or enter the dynamic {{email}} variable"
          }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          {
            id: "api_key",
            name: "API Access Key",
            type: "text",
            placeholder: "Enter MailboxLayer API Access Key"
          }
        ]
      }
    ]
  },
  {
    id: "fluentcrm",
    name: "FluentCRM",
    icon: "FC",
    iconBg: "bg-blue-700",
    actions: [
      {
        id: "create_contact",
        name: "Create/Update Contact",
        description: "Add a new contact or update an existing one in FluentCRM.",
        fields: [
          {
            id: "email",
            name: "Email Address",
            type: "text",
            placeholder: "e.g., {{fields.your-email}} or {email}"
          },
          {
            id: "first_name",
            name: "First Name",
            type: "text",
            placeholder: "e.g., {{fields.your-name}} or {first_name}"
          },
          {
            id: "last_name",
            name: "Last Name",
            type: "text",
            placeholder: "e.g., {last_name}"
          },
          {
            id: "status",
            name: "Status",
            type: "select",
            options: [
              { id: "subscribed", name: "Subscribed" },
              { id: "pending", name: "Pending" },
              { id: "unsubscribed", name: "Unsubscribed" },
            ],
            defaultValue: "subscribed"
          }
        ]
      }
    ]
  },
  {
    id: "openai",
    name: "OpenAI",
    icon: "OA",
    iconBg: "bg-emerald-600",
    actions: [
      {
        id: "chat_completion",
        name: "Chat Completion",
        description: "Generate text using OpenAI models.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-..." },
          {
            id: "model",
            name: "Model",
            type: "select",
            options: [
              { id: "gpt-3.5-turbo", name: "GPT-3.5 Turbo" },
              { id: "gpt-4", name: "GPT-4" },
              { id: "gpt-4o", name: "GPT-4o" },
            ],
            defaultValue: "gpt-3.5-turbo"
          },
          {
            id: "prv_retrn_resp", name: "Preview Response in Logs", type: "checkbox", defaultValue: true
          },
          { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
          { id: "max_tokens", name: "Max Tokens", type: "number", defaultValue: 1000 },
          { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-..." }
        ]
      }
    ]
  },
  {
    id: "claude",
    name: "Claude",
    icon: "CL",
    iconBg: "bg-amber-700",
    actions: [
      {
        id: "chat_completion",
        name: "Chat Completion",
        description: "Generate text using Anthropic Claude models.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-ant-..." },
          {
            id: "model",
            name: "Model",
            type: "select",
            options: [
              { id: "claude-3-5-sonnet-20240620", name: "Claude 3.5 Sonnet" },
              { id: "claude-3-opus-20240229", name: "Claude 3 Opus" },
              { id: "claude-3-haiku-20240307", name: "Claude 3 Haiku" },
            ],
            defaultValue: "claude-3-5-sonnet-20240620"
          },
          { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
          { id: "max_tokens", name: "Max Tokens", type: "number", defaultValue: 1000 },
          { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-ant-..." }
        ]
      }
    ]
  },
  {
    id: "gemini",
    name: "Gemini",
    icon: "GE",
    iconBg: "bg-blue-500",
    actions: [
      {
        id: "chat_completion",
        name: "Chat Completion",
        description: "Generate text using Google Gemini models.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "AIza..." },
          {
            id: "model",
            name: "Model",
            type: "select",
            options: [
              { id: "gemini-1.5-flash", name: "Gemini 1.5 Flash" },
              { id: "gemini-1.5-pro", name: "Gemini 1.5 Pro" },
              { id: "gemini-1.5-flash-8b", name: "Gemini 1.5 Flash-8B" },
              { id: "gemini-2.0-flash", name: "Gemini 2.0 Flash" },
            ],
            defaultValue: "gemini-1.5-flash"
          },
          {
            id: "prv_retrn_resp", name: "Preview Response in Logs ( put the '$response' variable to use the Preview Response )", type: "checkbox", defaultValue: true
          },
          { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
          { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "AIza..." }
        ]
      }
    ]
  },
  {
    id: "mistral",
    name: "Mistral AI",
    icon: "MI",
    iconBg: "bg-orange-600",
    actions: [
      {
        id: "chat_completion",
        name: "Chat Completion",
        description: "Generate text using Mistral AI models.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "Enter Mistral API Key" },
          {
            id: "model",
            name: "Model",
            type: "select",
            options: [
              { id: "mistral-tiny", name: "Mistral Tiny" },
              { id: "mistral-small", name: "Mistral Small" },
              { id: "mistral-medium", name: "Mistral Medium" },
              { id: "mistral-large-latest", name: "Mistral Large" },
            ],
            defaultValue: "mistral-tiny"
          },
          { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
          { id: "max_tokens", name: "Max Tokens", type: "number", defaultValue: 1000 },
          { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "Enter Mistral API Key" }
        ]
      }
    ]
  },
  // {
  //   id: "groq",
  //   name: "Groq AI",
  //   icon: "GR",
  //   iconBg: "bg-red-500",
  //   actions: [
  //     {
  //       id: "chat_completion",
  //       name: "Chat Completion",
  //       description: "Generate text using Groq AI models.",
  //       fields: [
  //         { id: "api_key", name: "API Key", type: "text", placeholder: "gsk_..." },
  //         { 
  //           id: "model", 
  //           name: "Model", 
  //           type: "select", 
  //           options: [
  //             { id: "llama3-8b-8192", name: "Llama 3 8B" },
  //             { id: "llama3-70b-8192", name: "Llama 3 70B" },
  //             { id: "mixtral-8x7b-32768", name: "Mixtral 8x7B" },
  //             { id: "gemma-7b-it", name: "Gemma 7B" },
  //           ],
  //           defaultValue: "llama3-8b-8192" 
  //         },
  //         { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
  //         { id: "max_tokens", name: "Max Tokens", type: "number", defaultValue: 1000 },
  //         { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
  //       ]
  //     },
  //     {
  //       id: "test_connection",
  //       name: "Test Connection",
  //       description: "Verify your API key is valid.",
  //       fields: [
  //         { id: "api_key", name: "API Key", type: "text", placeholder: "gsk_..." }
  //       ]
  //     }
  //   ]
  // },
  {
    id: "grok",
    name: "Grok AI",
    icon: "GK",
    iconBg: "bg-slate-900",
    actions: [
      {
        id: "chat_completion",
        name: "Chat Completion",
        description: "Generate text using xAI Grok models.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "xai-..." },
          {
            id: "model",
            name: "Model",
            type: "select",
            options: [
              { id: "grok-2-1212", name: "Grok-2" },
              { id: "grok-2-latest", name: "Grok-2 (Latest)" },
              { id: "grok-beta", name: "Grok Beta" },
            ],
            defaultValue: "grok-2-1212"
          },
          { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
          { id: "max_tokens", name: "Max Tokens", type: "number", defaultValue: 1000 },
          { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your xAI API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "xai-..." }
        ]
      }
    ]
  },
  {
    id: "openrouter",
    name: "OpenRouter",
    icon: "OR",
    iconBg: "bg-indigo-600",
    actions: [
      {
        id: "chat_completion",
        name: "Chat Completion",
        description: "Generate text using OpenRouter models.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-or-..." },
          {
            id: "model",
            name: "Model",
            type: "text",
            placeholder: "e.g., openai/gpt-3.5-turbo",
            defaultValue: "openai/gpt-3.5-turbo"
          },
          { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
          { id: "max_tokens", name: "Max Tokens", type: "number", defaultValue: 1000 },
          { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-or-..." }
        ]
      }
    ]
  },
  {
    id: "fluentcrm",
    name: "FluentCRM",
    icon: "FC",
    iconBg: "bg-blue-600",
    actions: [
      {
        id: "test_connection1",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-or-..." },
          {
            id: "model",
            name: "Model",
            type: "text",
            placeholder: "e.g., openai/gpt-3.5-turbo",
            defaultValue: "openai/gpt-3.5-turbo"
          },
          { id: "prompt", name: "Prompt", type: "textarea", placeholder: "Enter your prompt here" },
          { id: "max_tokens", name: "Max Tokens", type: "number", defaultValue: 1000 },
          { id: "temperature", name: "Temperature", type: "number", defaultValue: 0.7 }
        ]
      },
      {
        id: "test_connection",
        name: "Test Connection",
        description: "Verify your API key is valid.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "sk-or-..." }
        ]
      }
    ]
  },
  {
    id: "google_sheets",
    name: "Google Sheets",
    icon: "GS",
    iconBg: "bg-green-600",
    actions: [
      {
        id: "gs_add_row",
        name: "Add Row (Save Form Data)",
        description: "Append a new row of data to a spreadsheet.",
        fields: [
          {
            id: "service_account_json",
            name: "Service Account JSON",
            type: "textarea",
            placeholder: 'Paste the entire contents of your Google Service Account JSON key file here',
          },
          {
            id: "spreadsheet_id",
            name: "Spreadsheet ID",
            type: "text",
            placeholder: "e.g., 1BxiMVs0XRYFgPNmi...",
            description: "The long random string in the middle of your Google Sheet URL"
          },
          {
            id: "sheet_name",
            name: "Sheet Name",
            type: "text",
            placeholder: "e.g., Sheet1 or tab name (Leave empty for Sheet1)"
          },
          {
            id: "row_data",
            name: "Row Data (Optional)",
            type: "textarea",
            placeholder: "Leave empty to automatically add all form fields as columns.",
            description: "Provide the values for each column. Separate with commas or new lines. If left empty, all submitted form fields will be added automatically."
          }
        ]
      },
      {
        id: "gs_update_row",
        name: "Update Row",
        description: "Update an existing row in your spreadsheet by row number.",
        fields: [
          {
            id: "service_account_json",
            name: "Service Account JSON",
            type: "textarea",
            placeholder: 'Paste the entire contents of your Google Service Account JSON key file here',
          },
          {
            id: "spreadsheet_id",
            name: "Spreadsheet ID",
            type: "text",
            placeholder: "e.g., 1BxiMVs0XRYFgPNmi..."
          },
          {
            id: "sheet_name",
            name: "Sheet Name",
            type: "text",
            placeholder: "e.g., Sheet1"
          },
          {
            id: "row_number",
            name: "Row Number",
            type: "number",
            placeholder: "e.g., 2"
          },
          {
            id: "row_data",
            name: "Row Data",
            type: "textarea",
            placeholder: "Enter updated values on a new line or separated by commas.",
            description: "New values for this row. Use commas or new lines to separate columns."
          }
        ]
      },
      {
        id: "gs_clear_range",
        name: "Clear Range",
        description: "Clear data from a specific range.",
        fields: [
          {
            id: "service_account_json",
            name: "Service Account JSON",
            type: "textarea",
            placeholder: 'Paste the entire contents of your Service Account JSON key',
          },
          {
            id: "spreadsheet_id",
            name: "Spreadsheet ID",
            type: "text",
            placeholder: "e.g., 1BxiMVs0XRYFgPNmi..."
          },
          {
            id: "range",
            name: "Range to Clear",
            type: "text",
            placeholder: "e.g., Sheet1!A2:E10"
          }
        ]
      },
      {
        id: "gs_get_values",
        name: "Get Values (Read Data)",
        description: "Read values from a specific range.",
        fields: [
          {
            id: "service_account_json",
            name: "Service Account JSON",
            type: "textarea",
            placeholder: 'Paste the entire contents of your Service Account JSON key',
          },
          {
            id: "spreadsheet_id",
            name: "Spreadsheet ID",
            type: "text",
            placeholder: "e.g., 1BxiMVs0XRYFgPNmi..."
          },
          {
            id: "range",
            name: "Range to Read",
            type: "text",
            placeholder: "e.g., Sheet1!A2:C5"
          }
        ]
      }
    ]
  },
  {
    id: "iterator",
    name: "Iterator",
    icon: "↻",
    iconBg: "bg-amber-500",
    actions: [
      {
        id: "iterator_loop",
        name: "Loop (Iterator)",
        description: "Loop through each item in an array (e.g., order items). Connect to Iterator End to close the loop.",
        fields: [
          {
            id: "array_key",
            name: "Array Key",
            type: "text",
            placeholder: "e.g., items — the trigger data key holding the array to loop over"
          }
        ]
      }
    ]
  },
  {
    id: "iterator_end",
    name: "Iterator End",
    icon: "⏹",
    iconBg: "bg-amber-700",
    actions: [
      {
        id: "iterator_end",
        name: "Loop End",
        description: "Marks the end of an Iterator loop. Actions after this will run once, after all items have been processed."
      }
    ]
  },
  {
    id: "telegram",
    name: "Telegram",
    icon: "Tg",
    iconBg: "bg-blue-500",
    credentialConfig: {
      fields: [
        { id: "bot_token", name: "Bot Token", type: "text", placeholder: "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" }
      ]
    },
    actions: [
      {
        id: "tg_send_message",
        name: "Send a text message",
        description: "Send a text message via Telegram Bot API.",
        fields: [
          { id: "credential", name: "Credential", type: "credential" },
          { id: "chat_id", name: "Chat ID", type: "text", placeholder: "e.g., -1001234567890 or @channelname" },
          { id: "text", name: "Text", type: "textarea", placeholder: "Enter message text here" }
        ]
      }
    ]
  },
  {
    id: "whatsapp",
    name: "WhatsApp",
    icon: "WA",
    iconBg: "bg-green-500",
    credentialConfig: {
      fields: [
        { id: "access_token", name: "Access Token", type: "textarea", placeholder: "Your Meta/WhatsApp Business access token" }
      ]
    },
    actions: [
      {
        id: "wa_send_message",
        name: "Send Message",
        isPro: true,
        description: "Send a text message via the WhatsApp Cloud API (Meta).",
        fields: [
          { id: "credential", name: "Credential", type: "credential" },
          { id: "phone_number_id", name: "Sender Phone Number ID", type: "text", placeholder: "WhatsApp business phone number ID" },
          { id: "to", name: "Recipient Phone", type: "text", placeholder: "e.g., {billing_phone} or 601234567890 (country code, no +)" },
          { id: "message", name: "Text Body", type: "textarea", placeholder: "Hello {billing_name}, your order #{order_id} has been received." }
        ]
      },
      {
        id: "wa_send_template",
        name: "Send Template Message",
        isPro: true,
        description: "Send a pre-approved WhatsApp template message.",
        fields: [
          { id: "credential", name: "Credential", type: "credential" },
          { id: "phone_number_id", name: "Sender Phone Number ID", type: "text", placeholder: "WhatsApp business phone number ID" },
          { id: "to", name: "Recipient Phone", type: "text", placeholder: "e.g., {billing_phone} or 601234567890" },
          { id: "template_name", name: "Template Name", type: "text", placeholder: "e.g., order_confirmation" },
          { id: "language_code", name: "Language Code", type: "text", placeholder: "e.g., en_US" },
          { id: "body_parameters", name: "Body Parameters", type: "textarea", placeholder: "One value per line" }
        ]
      }
    ]
  },
  {
    id: "instagram",
    name: "Instagram",
    icon: "Ig",
    iconBg: "bg-pink-500",
    credentialConfig: {
      fields: [
        { id: "access_token", name: "Access Token", type: "textarea", placeholder: "Your Instagram/Facebook Graph API access token" }
      ]
    },
    actions: [
      {
        id: "ig_publish_photo",
        name: "Publish Photo",
        isPro: true,
        description: "Publish a photo to an Instagram Business account.",
        fields: [
          { id: "credential", name: "Credential", type: "credential" },
          { id: "ig_account_id", name: "Instagram Account ID", type: "text", placeholder: "17841400000000000" },
          { id: "image_url", name: "Image URL", type: "text", placeholder: "https://example.com/image.jpg OR Use {featured_image_url} for post thumbnail or enter a direct URL" },
          { id: "caption", name: "Caption", type: "textarea", placeholder: "Check out this awesome photo! OR Use {post_content} for post body, {post_title} for title, {post_url} for link" }
        ]
      }
    ]
  },
  {
    id: "linkedin",
    name: "LinkedIn",
    icon: "In",
    iconBg: "bg-sky-600",
    credentialConfig: {
      fields: [
        { id: "access_token", name: "Access Token", type: "textarea", placeholder: "Your LinkedIn developer token" }
      ]
    },
    actions: [
      {
        id: "li_create_post",
        name: "Create Text Post",
        isPro: true,
        description: "Create a simple text post on a LinkedIn profile or organization page.",
        fields: [
          { id: "credential", name: "Credential", type: "credential" },
          { id: "author", name: "Author URN", type: "text", placeholder: "urn:li:person:1234567 or urn:li:organization:1234567" },
          { id: "text", name: "Message", type: "textarea", placeholder: "Excited to share this new update!..." }
        ]
      }
    ]
  },
  {
    id: "data_trsformer",
    name: "Data Transformer",
    icon: "DT",
    iconBg: "bg-purple-500",
    description: "Perform common data transformations like encoding, hashing, and stripping HTML tags.",
    actions: [
      {
        id: "data_transformer",
        name: "Data Transformer",
        fields: [
          { id: "Action", name: "Action", type: "select", options: [
            { id: "html_encode", name: "HTML Encode" },
            { id: "html_decode", name: "HTML Decode" },
            { id: "base64_encode", name: "Base64 Encode" },
            { id: "base64_decode", name: "Base64 Decode" },
            { id: "sha256", name: "SHA256 Hash" },
            { id: "sha1", name: "SHA1 Hash" },
            { id: "sha512", name: "SHA512 Hash" },
            { id: "hmac_sha256", name: "HMAC SHA256 (requires secret key)" },
            { id: "md5", name: "MD5 Hash" },
            { id: "strip_tags", name: "Strip HTML Tags" }
          ], defaultValue: "html_encode" },
          { id: "input", name: "Input value", type: "textarea", placeholder: "Enter the data you want to transform. You can use dynamic variables here." },
          { id: "secret_key", name: "Secret Key (for HMAC SHA256)", type: "text", placeholder: "Enter secret key for HMAC SHA256", dependsOn: { field: "Action", value: "hmac_sha256" } }
        ]
      }]
  
  },
  {
    id: "text_formatter",
    name: "Text Formatter",
    icon: "T",
    iconBg: "bg-teal-600",
    actions: [
      {
        id: "extract_pattern",
        name: "Extract Pattern",
        description: "Provide regular expression to extract string/text",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" },
          { id: "pattern", name: "Pattern (Regex)", type: "text", placeholder: "/(pattern)/i" }
        ]
      },
      {
        id: "find_text",
        name: "Find in Text",
        description: "Search text in the given content",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" },
          { id: "search", name: "Search For", type: "text", placeholder: "Text to find" }
        ]
      },
      {
        id: "replace_text",
        name: "Replace Text",
        description: "Search and replace text in the given content",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" },
          { id: "search", name: "Search For", type: "text", placeholder: "Text to find" },
          { id: "replace", name: "Replace With", type: "text", placeholder: "Replacement text" }
        ]
      },
      {
        id: "split_text",
        name: "Split Text",
        description: "Split the text on a character or word and return a segment",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" },
          { id: "delimiter", name: "Separator", type: "text", placeholder: "," },
          { id: "segment_index", name: "Segment Index", type: "number", placeholder: "0 for first, -1 for last", defaultValue: 0 }
        ]
      },
      {
        id: "default_value",
        name: "Default Value",
        description: "Return a default value if the text is empty",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" },
          { id: "default", name: "Default Value", type: "text", placeholder: "Fallback text" }
        ]
      },
      {
        id: "truncate",
        name: "Truncate",
        description: "Limit your text to a specific character length, and delete anything over that",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" },
          { id: "length", name: "Max Length", type: "number", placeholder: "100" },
          { id: "suffix", name: "Suffix", type: "text", placeholder: "...", defaultValue: "..." }
        ]
      },
      {
        id: "url_encode",
        name: "URL Encode",
        description: "Encodes text for use in URLs",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" }
        ]
      },
      {
        id: "url_decode",
        name: "URL Decode",
        description: "Decodes text from URL string",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" }
        ]
      },
      {
        id: "join_lines",
        name: "Join Lines",
        description: "Join multiline text into a single line with a chosen separator",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" },
          { id: "separator", name: "Separator", type: "text", placeholder: ", ", defaultValue: ", " }
        ]
      },
      {
        id: "html_to_markdown",
        name: "HTML to Markdown",
        description: "Convert HTML content to Markdown format",
        fields: [
          { id: "text", name: "HTML Text", type: "textarea", placeholder: "<p>Hello</p>" },
          { id: "save_media", name: "Save Media to Media Library?", type: "checkbox", defaultValue: false }
        ]
      },
      {
        id: "markdown_to_html",
        name: "Markdown to HTML",
        description: "Convert Markdown text to HTML",
        fields: [
          { id: "text", name: "Markdown Text", type: "textarea", placeholder: "# Hello" }
        ]
      },
      {
        id: "generate_slug",
        name: "Generate Slug",
        description: "Convert text to a URL-friendly slug",
        fields: [
          { id: "text", name: "Text", type: "textarea", placeholder: "Input text" }
        ]
      }
    ]
  },
  {
    id: "xml_parser",
    name: "XML Parser",
    icon: "XML",
    iconBg: "bg-amber-600",
    actions: [
      {
        id: "parse_xml_file",
        name: "Parse XML File",
        description: "Fetch and parse an XML file URL into JSON data.",
        fields: [
          {
            id: "xml_url",
            name: "XML File URL",
            type: "text",
            placeholder: "https://example.com/data.xml"
          }
        ]
      }
    ]
  },
  {
    id: "email_parser",
    name: "Email Parser",
    icon: "EP",
    iconBg: "bg-indigo-500",
    actions: [
      {
        id: "parse_email_content",
        name: "Parse Email Content",
        isPro: true,
        description: "Parse raw email text to extract emails and URLs.",
        fields: [
          {
            id: "email_content",
            name: "Email Content",
            type: "textarea",
            placeholder: "Pass the raw email content here"
          }
        ]
      },
      {
        id: "fetch_emails_imap",
        name: "Fetch Emails (IMAP)",
        isPro: true,
        description: "Fetch emails from an IMAP server.",
        fields: [
          { id: "host", name: "IMAP Host", type: "text", placeholder: "imap.gmail.com" },
          { id: "port", name: "Port", type: "number", placeholder: "993", defaultValue: 993 },
          {
            id: "encryption",
            name: "Encryption",
            type: "select",
            options: [
              { id: "/imap/ssl", name: "SSL" },
              { id: "/imap/tls", name: "TLS" },
              { id: "/imap", name: "None" }
            ],
            defaultValue: "/imap/ssl"
          },
          { id: "username", name: "Username", type: "text", placeholder: "user@example.com" },
          { id: "password", name: "Password", type: "password", placeholder: "Your password or app password" },
          { id: "folder", name: "Folder", type: "text", placeholder: "INBOX", defaultValue: "INBOX" },
          { id: "search", name: "Search Criteria", type: "text", placeholder: "UNSEEN", defaultValue: "UNSEEN" },
          { id: "limit", name: "Limit", type: "number", placeholder: "5", defaultValue: 5 }
        ]
      }
    ]
  },
  {
    id: "curl_importer",
    name: "cURL Importer",
    icon: ">_",
    iconBg: "bg-slate-800",
    actions: [
      {
        id: "execute_curl",
        name: "Execute cURL Command",
        isPro: true,
        description: "Parse and execute a raw cURL command and return the JSON response.",
        fields: [
          {
            id: "curl_command",
            name: "cURL Command",
            type: "textarea",
            placeholder: "curl -X POST https://api.example.com -H 'Content-Type: application/json' -d '{\"key\":\"value\"}'"
          }
        ]
      }
    ]
  },
  {
    id: "json_parser",
    name: "JSON Parser",
    icon: "{}",
    iconBg: "bg-yellow-600",
    actions: [
      {
        id: "parse_json_url",
        name: "Parse JSON from URL",
        isPro: true,
        description: "Fetch and parse a JSON file from a URL into structured data.",
        fields: [
          {
            id: "json_url",
            name: "JSON File URL",
            type: "text",
            placeholder: "https://example.com/data.json"
          }
        ]
      },
      {
        id: "parse_json_text",
        name: "Parse JSON Text",
        isPro: true,
        description: "Parse raw JSON text into structured data.",
        fields: [
          {
            id: "json_text",
            name: "JSON Text",
            type: "textarea",
            placeholder: "{\"key\":\"value\"}"
          }
        ]
      }
    ]
  },
  {
    id: "webpage_parser",
    name: "Webpage Parser",
    icon: "🌐",
    iconBg: "bg-teal-500",
    actions: [
      {
        id: "parse_webpage_url",
        name: "Parse Webpage URL",
        isPro: true,
        description: "Fetch a URL and parse its title, description, and raw text content.",
        fields: [
          {
            id: "webpage_url",
            name: "Webpage URL",
            type: "text",
            placeholder: "https://example.com"
          }
        ]
      }
    ]
  },
  {
    id: "csv_parser",
    name: "CSV Parser",
    icon: "CSV",
    iconBg: "bg-green-700",
    actions: [
      {
        id: "parse_csv_url",
        name: "Parse CSV from URL",
        isPro: true,
        description: "Fetch and parse a CSV file from a URL into structured row data.",
        fields: [
          {
            id: "csv_url",
            name: "CSV File URL",
            type: "text",
            placeholder: "https://example.com/data.csv"
          },
          {
            id: "delimiter",
            name: "Delimiter",
            type: "text",
            placeholder: "e.g. , or ; or \\t",
            defaultValue: ","
          },
          {
            id: "has_header",
            name: "First row is header?",
            type: "checkbox",
            defaultValue: true
          },
          {
            id: "row_limit",
            name: "Row Limit (0 = unlimited)",
            type: "number",
            placeholder: "0",
            defaultValue: 0
          }
        ]
      },
      {
        id: "parse_csv_text",
        name: "Parse CSV Text",
        isPro: true,
        description: "Parse raw CSV text (e.g. from a previous action) into structured row data.",
        fields: [
          {
            id: "csv_text",
            name: "CSV Text",
            type: "textarea",
            placeholder: "name,email,phone\nJohn,john@example.com,123456"
          },
          {
            id: "delimiter",
            name: "Delimiter",
            type: "text",
            placeholder: "e.g. , or ; or \\t",
            defaultValue: ","
          },
          {
            id: "has_header",
            name: "First row is header?",
            type: "checkbox",
            defaultValue: true
          },
          {
            id: "row_limit",
            name: "Row Limit (0 = unlimited)",
            type: "number",
            placeholder: "0",
            defaultValue: 0
          }
        ]
      }
    ]
  },
  {
    id: "unit_converter",
    name: "Unit Converter",
    icon: "U",
    iconBg: "bg-indigo-500",
    actions: [
      {
        id: "length_conversion",
        name: "Length Conversion",
        description: "Convert between meters, feet, inches, kilometers, miles, yards, centimeters, millimeters, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "mm", name: "Millimeter" },
              { id: "cm", name: "Centimeter" },
              { id: "m", name: "Meter" },
              { id: "km", name: "Kilometer" },
              { id: "in", name: "Inch" },
              { id: "ft", name: "Foot" },
              { id: "yd", name: "Yard" },
              { id: "mi", name: "Mile" }
            ],
            defaultValue: "m"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "mm", name: "Millimeter" },
              { id: "cm", name: "Centimeter" },
              { id: "m", name: "Meter" },
              { id: "km", name: "Kilometer" },
              { id: "in", name: "Inch" },
              { id: "ft", name: "Foot" },
              { id: "yd", name: "Yard" },
              { id: "mi", name: "Mile" }
            ],
            defaultValue: "ft"
          }
        ]
      },
      {
        id: "weight_conversion",
        name: "Weight Conversion",
        description: "Convert between kilograms, pounds, ounces, grams, tons, milligrams, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "mg", name: "Milligram" },
              { id: "g", name: "Gram" },
              { id: "kg", name: "Kilogram" },
              { id: "ton", name: "Ton" },
              { id: "oz", name: "Ounce" },
              { id: "lb", name: "Pound" }
            ],
            defaultValue: "kg"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "mg", name: "Milligram" },
              { id: "g", name: "Gram" },
              { id: "kg", name: "Kilogram" },
              { id: "ton", name: "Ton" },
              { id: "oz", name: "Ounce" },
              { id: "lb", name: "Pound" }
            ],
            defaultValue: "lb"
          }
        ]
      },
      {
        id: "temperature_conversion",
        name: "Temperature Conversion",
        description: "Convert between Celsius, Fahrenheit, and Kelvin.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "c", name: "Celsius" },
              { id: "f", name: "Fahrenheit" },
              { id: "k", name: "Kelvin" }
            ],
            defaultValue: "c"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "c", name: "Celsius" },
              { id: "f", name: "Fahrenheit" },
              { id: "k", name: "Kelvin" }
            ],
            defaultValue: "f"
          }
        ]
      },
      {
        id: "volume_conversion",
        name: "Volume Conversion",
        description: "Convert between liters, gallons, milliliters, fluid ounces, cups, pints, quarts, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "ml", name: "Milliliter" },
              { id: "l", name: "Liter" },
              { id: "gal", name: "Gallon" },
              { id: "floz", name: "Fluid Ounce" },
              { id: "cup", name: "Cup" },
              { id: "pt", name: "Pint" },
              { id: "qt", name: "Quart" }
            ],
            defaultValue: "l"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "ml", name: "Milliliter" },
              { id: "l", name: "Liter" },
              { id: "gal", name: "Gallon" },
              { id: "floz", name: "Fluid Ounce" },
              { id: "cup", name: "Cup" },
              { id: "pt", name: "Pint" },
              { id: "qt", name: "Quart" }
            ],
            defaultValue: "gal"
          }
        ]
      },
      {
        id: "area_conversion",
        name: "Area Conversion",
        description: "Convert between square meters, square feet, acres, hectares, square kilometers, square miles, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "sqm", name: "Square Meter" },
              { id: "sqft", name: "Square Foot" },
              { id: "ac", name: "Acre" },
              { id: "ha", name: "Hectare" },
              { id: "sqkm", name: "Square Kilometer" },
              { id: "sqmi", name: "Square Mile" }
            ],
            defaultValue: "sqm"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "sqm", name: "Square Meter" },
              { id: "sqft", name: "Square Foot" },
              { id: "ac", name: "Acre" },
              { id: "ha", name: "Hectare" },
              { id: "sqkm", name: "Square Kilometer" },
              { id: "sqmi", name: "Square Mile" }
            ],
            defaultValue: "ac"
          }
        ]
      },
      {
        id: "speed_conversion",
        name: "Speed Conversion",
        description: "Convert between km/h, mph, m/s, knots, ft/s, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "kmh", name: "km/h" },
              { id: "mph", name: "mph" },
              { id: "ms", name: "m/s" },
              { id: "knot", name: "Knots" },
              { id: "fts", name: "ft/s" }
            ],
            defaultValue: "kmh"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "kmh", name: "km/h" },
              { id: "mph", name: "mph" },
              { id: "ms", name: "m/s" },
              { id: "knot", name: "Knots" },
              { id: "fts", name: "ft/s" }
            ],
            defaultValue: "mph"
          }
        ]
      },
      {
        id: "pressure_conversion",
        name: "Pressure Conversion",
        description: "Convert between pascals, bars, psi, atmospheres, torr, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "pa", name: "Pascal" },
              { id: "bar", name: "Bar" },
              { id: "psi", name: "PSI" },
              { id: "atm", name: "Atmosphere" },
              { id: "torr", name: "Torr" }
            ],
            defaultValue: "pa"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "pa", name: "Pascal" },
              { id: "bar", name: "Bar" },
              { id: "psi", name: "PSI" },
              { id: "atm", name: "Atmosphere" },
              { id: "torr", name: "Torr" }
            ],
            defaultValue: "bar"
          }
        ]
      },
      {
        id: "energy_conversion",
        name: "Energy Conversion",
        description: "Convert between joules, calories, kilowatt-hours, BTU, electron volts, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "j", name: "Joule" },
              { id: "cal", name: "Calorie" },
              { id: "kwh", name: "Kilowatt-hour" },
              { id: "btu", name: "BTU" },
              { id: "ev", name: "Electron Volt" }
            ],
            defaultValue: "j"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "j", name: "Joule" },
              { id: "cal", name: "Calorie" },
              { id: "kwh", name: "Kilowatt-hour" },
              { id: "btu", name: "BTU" },
              { id: "ev", name: "Electron Volt" }
            ],
            defaultValue: "cal"
          }
        ]
      },
      {
        id: "power_conversion",
        name: "Power Conversion",
        description: "Convert between watts, kilowatts, horsepower, BTU/hour, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "w", name: "Watt" },
              { id: "kw", name: "Kilowatt" },
              { id: "hp", name: "Horsepower" },
              { id: "btuh", name: "BTU/hour" }
            ],
            defaultValue: "w"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "w", name: "Watt" },
              { id: "kw", name: "Kilowatt" },
              { id: "hp", name: "Horsepower" },
              { id: "btuh", name: "BTU/hour" }
            ],
            defaultValue: "kw"
          }
        ]
      },
      {
        id: "data_size_conversion",
        name: "Data Size Conversion",
        description: "Convert between bytes, KB, MB, GB, TB, PB, bits, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "bit", name: "Bit" },
              { id: "b", name: "Byte" },
              { id: "kb", name: "Kilobyte (KB)" },
              { id: "mb", name: "Megabyte (MB)" },
              { id: "gb", name: "Gigabyte (GB)" },
              { id: "tb", name: "Terabyte (TB)" },
              { id: "pb", name: "Petabyte (PB)" }
            ],
            defaultValue: "mb"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "bit", name: "Bit" },
              { id: "b", name: "Byte" },
              { id: "kb", name: "Kilobyte (KB)" },
              { id: "mb", name: "Megabyte (MB)" },
              { id: "gb", name: "Gigabyte (GB)" },
              { id: "tb", name: "Terabyte (TB)" },
              { id: "pb", name: "Petabyte (PB)" }
            ],
            defaultValue: "gb"
          }
        ]
      },
      {
        id: "angle_conversion",
        name: "Angle Conversion",
        description: "Convert between degrees, radians, gradians, arc minutes, arc seconds, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "deg", name: "Degree" },
              { id: "rad", name: "Radian" },
              { id: "grad", name: "Gradian" },
              { id: "min", name: "Arc Minute" },
              { id: "sec", name: "Arc Second" }
            ],
            defaultValue: "deg"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "deg", name: "Degree" },
              { id: "rad", name: "Radian" },
              { id: "grad", name: "Gradian" },
              { id: "min", name: "Arc Minute" },
              { id: "sec", name: "Arc Second" }
            ],
            defaultValue: "rad"
          }
        ]
      },
      {
        id: "frequency_conversion",
        name: "Frequency Conversion",
        description: "Convert between hertz, kilohertz, megahertz, gigahertz, RPM, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "hz", name: "Hertz" },
              { id: "khz", name: "Kilohertz" },
              { id: "mhz", name: "Megahertz" },
              { id: "ghz", name: "Gigahertz" },
              { id: "rpm", name: "RPM" }
            ],
            defaultValue: "hz"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "hz", name: "Hertz" },
              { id: "khz", name: "Kilohertz" },
              { id: "mhz", name: "Megahertz" },
              { id: "ghz", name: "Gigahertz" },
              { id: "rpm", name: "RPM" }
            ],
            defaultValue: "khz"
          }
        ]
      },
      {
        id: "fuel_economy_conversion",
        name: "Fuel Economy Conversion",
        description: "Convert between MPG, L/100km, km/L, miles/gallon, etc.",
        fields: [
          { id: "value", name: "Value", type: "text", placeholder: "e.g., 10 or {{token}}" },
          {
            id: "from",
            name: "From Unit",
            type: "select",
            options: [
              { id: "mpg", name: "MPG (US)" },
              { id: "mpg_imp", name: "MPG (Imperial)" },
              { id: "l100", name: "L/100km" },
              { id: "kml", name: "km/L" }
            ],
            defaultValue: "mpg"
          },
          {
            id: "to",
            name: "To Unit",
            type: "select",
            options: [
              { id: "mpg", name: "MPG (US)" },
              { id: "mpg_imp", name: "MPG (Imperial)" },
              { id: "l100", name: "L/100km" },
              { id: "kml", name: "km/L" }
            ],
            defaultValue: "l100"
          }
        ]
      }
    ]
  },
  {
    id: "fluent_forms",
    name: "Fluent Forms",
    isTrigger: true,
    icon: "FF",
    iconBg: "bg-green-600",
    actions: [
      { id: "ff_form_submit", name: "Form submitted" }
    ]
  },
  {
    id: "mailrefine",
    name: "MailRefine",
    icon: "MR",
    iconBg: "bg-violet-600",
    actions: [
      {
        id: "mr_verify_email",
        name: "Verify Email",
        description: "Verify if an email address is valid and deliverable using MailRefine.",
        fields: [
          { id: "api_key", name: "API Key", type: "text", placeholder: "Enter MailRefine API Key" },
          { id: "email", name: "Email to Verify", type: "text", placeholder: "e.g., {email} or {{fields.email}}" }
        ]
      }
    ]
  },
  {
    id: "filters",
    name: "Filters",
    icon: "FT",
    iconBg: "bg-yellow-500",
    actions: [
      {
        id: "filter_condition",
        name: "Filter / Condition",
        description: "Only continue the workflow if the specified condition is met.",
        fields: [
          { id: "field", name: "Field", type: "text", placeholder: "e.g., {email_valid} or {{status}}" },
          {
            id: "operator",
            name: "Operator",
            type: "select",
            options: [
              { id: "equals", name: "Equals" },
              { id: "not_equals", name: "Not Equals" },
              { id: "contains", name: "Contains" },
              { id: "not_contains", name: "Does Not Contain" },
              { id: "is_true", name: "Is True" },
              { id: "is_false", name: "Is False" }
            ],
            defaultValue: "equals"
          },
          { id: "value", name: "Value", type: "text", placeholder: "e.g., true or valid" }
        ]
      }
    ]
  },
  {
    id: "csv_creator",
    name: "CSV Creator",
    icon: "CSV",
    iconBg: "bg-emerald-700",
    categories: [
      {
        id: "files",
        name: "File Actions",
        actions: [
          {
            id: "csv_from_table",
            name: "Create CSV from Table",
            description: "Export records from a custom table to a CSV file saved in the media library. Returns {csv_url}, {csv_attachment_id}, {csv_filename}, {csv_row_count}.",
            fields: [
              {
                id: "table_id",
                name: "Table",
                type: "select",
                optionsUrl: "/tables",
                placeholder: "Select a table…"
              },
              {
                id: "filter_col",
                name: "Filter Column (optional)",
                type: "text",
                placeholder: "column_slug — leave blank to export all rows"
              },
              {
                id: "filter_val",
                name: "Filter Value (optional)",
                type: "text",
                placeholder: "e.g. active or {status}"
              },
              {
                id: "filename",
                name: "Filename (optional)",
                type: "text",
                placeholder: "orders_2024.csv — leave blank for auto"
              }
            ]
          },
          {
            id: "csv_from_json",
            name: "Create CSV from JSON",
            description: "Convert a JSON array of objects to a CSV file. Pipe {response} from an AI or API action. Returns {csv_url}, {csv_attachment_id}, {csv_filename}, {csv_row_count}.",
            fields: [
              {
                id: "json_data",
                name: "JSON Data",
                type: "textarea",
                placeholder: "[{\"name\":\"Alice\",\"email\":\"alice@example.com\"},{\"name\":\"Bob\",\"email\":\"bob@example.com\"}]\n\nTip: use {response} to pipe output from an AI or API action."
              },
              {
                id: "filename",
                name: "Filename (optional)",
                type: "text",
                placeholder: "report.csv — leave blank for auto"
              }
            ]
          },
          {
            id: "csv_from_array",
            name: "Create CSV from Array",
            description: "Convert a JSON array of arrays to a CSV file with optional custom headers. Returns {csv_url}, {csv_attachment_id}, {csv_filename}, {csv_row_count}.",
            fields: [
              {
                id: "array_data",
                name: "Array Data (JSON)",
                type: "textarea",
                placeholder: "[[\"Alice\",\"alice@example.com\"],[\"Bob\",\"bob@example.com\"]]\n\nTip: use {response} or {items} to pipe data from a previous action."
              },
              {
                id: "headers",
                name: "Column Headers (comma-separated, optional)",
                type: "text",
                placeholder: "Name, Email, Phone — leave blank for no header row"
              },
              {
                id: "filename",
                name: "Filename (optional)",
                type: "text",
                placeholder: "export.csv — leave blank for auto"
              }
            ]
          }
        ]
      }
    ]
  },
  {
    id: "tables",
    name: "Tables",
    icon: "TB",
    iconBg: "bg-indigo-600",
    isTrigger: true,
    categories: [
      {
        id: "triggers",
        name: "Triggers",
        actions: [
          {
            id: "trigger_button",
            name: "Trigger Workflow Button Clicked",
            description: "Fires when the 'Trigger Workflow' button is clicked in a table shortcode. Available tokens: {table_id}, {table_name}, {record_id}, and all column values."
          },
          {
            id: "new_record",
            name: "New Record",
            description: "Fires when a new row is inserted into a table. Available tokens: {table_id}, {table_name}, {record_id}, and all column values."
          },
          {
            id: "updated_record",
            name: "Updated Record",
            description: "Fires when a row is updated. Available tokens: {table_id}, {table_name}, {record_id}, new column values, and {old_<column_slug>} for previous values."
          },
          {
            id: "deleted_record",
            name: "Deleted Record",
            description: "Fires when a row is deleted. Available tokens: {table_id}, {table_name}, {record_id}, and the last known column values."
          },
          {
            id: "updated_cell",
            name: "Updated Cell",
            description: "Fires once per changed cell when a row is updated. Available tokens: {table_id}, {table_name}, {record_id}, {column_slug}, {old_value}, {new_value}."
          }
        ]
      },
      {
        id: "records",
        name: "Records",
        actions: [
          {
            id: "tables_insert_record",
            name: "Insert Record",
            description: "Insert a new row into one of your custom tables.",
            fields: [
              {
                id: "table_id",
                name: "Table",
                type: "select",
                optionsUrl: "/tables",
                placeholder: "Select a table…"
              },
              {
                id: "record_data",
                name: "Field Values",
                type: "table_columns_mapper",
                tableIdField: "table_id"
              }
            ]
          },
          {
            id: "tables_update_record",
            name: "Update Record",
            description: "Update an existing row in one of your custom tables.",
            fields: [
              {
                id: "table_id",
                name: "Table",
                type: "select",
                optionsUrl: "/tables",
                placeholder: "Select a table…"
              },
              {
                id: "row_id",
                name: "Record ID",
                type: "text",
                placeholder: "Row to update — use {record_id} or a number"
              },
              {
                id: "record_data",
                name: "Field Values",
                type: "table_columns_mapper",
                tableIdField: "table_id"
              }
            ]
          }
        ]
      }
    ]
  },
  {
    id: "workflow",
    name: "Workflows",
    icon: "WF",
    iconBg: "bg-teal-600",
    isTrigger: true,
    categories: [
      {
        id: "triggers",
        name: "Triggers",
        actions: [
          {
            id: "sub_workflow_trigger",
            name: "Sub-Workflow Trigger",
            description: "Starts the workflow when called from another workflow. Use this to create reusable modular workflows."
          }
        ]
      },
      {
        id: "actions",
        name: "Actions",
        actions: [
          {
            id: "call_sub_workflow",
            name: "Call Sub-Workflow",
            description: "Execute another workflow and use its returned data.",
            fields: [
              {
                id: "workflow_id",
                name: "Select Sub-Workflow",
                type: "select",
                optionsUrl: "/workflows",
                placeholder: "Choose a workflow to call…"
              },
              {
                id: "inputs",
                name: "Map Inputs",
                type: "keyvalue",
                placeholder: "Add Input (e.g. email: {customer_email})"
              }
            ]
          },
          {
            id: "return_values",
            name: "Return Values",
            description: "Stop the current sub-workflow and return specified values to the parent workflow.",
            fields: [
              {
                id: "values",
                name: "Return Values",
                type: "keyvalue",
                placeholder: "Add Value (e.g. status: success)"
              }
            ]
          }
        ]
      }
    ]
  },
  {
    id: "delay",
    name: "Delay",
    icon: "⏱",
    iconBg: "bg-slate-500",
    description: "Pause the workflow for a set duration before continuing to the next step.",
    actions: [
      {
        id: "delay_execution",
        name: "Wait / Delay",
        description: "Pause workflow execution for a configurable time (minutes, hours, or days) before continuing.",
        fields: [
          {
            id: "delay_amount",
            name: "Delay Amount",
            type: "number",
            placeholder: "e.g., 5"
          },
          {
            id: "delay_unit",
            name: "Unit",
            type: "select",
            options: [
              { id: "minutes", name: "Minutes" },
              { id: "hours", name: "Hours" },
              { id: "days", name: "Days" }
            ],
            defaultValue: "days"
          }
        ]
      }
    ]
  },
  {
    id: "wpbot",
    name: "WPBot",
    isTrigger: true,
    icon: "WB",
    iconBg: "bg-blue-500",
    categories: [
      {
        id: "triggers",
        name: "Triggers",
        actions: [
          {
            id: "wpbot_chat_session_saved",
            name: "WPBot Chat Session Saved"
          },
          {
            id: "conversationalforms_submit",
            name: "Conversational Forms Submitted",
            fields: [
              {
                id: "form_id",
                name: "Specific Form",
                type: "select",
                optionsUrl: "/conversational-forms",
                placeholder: "All forms (leave empty to trigger on any)"
              },
              {
                id: "specific_fields",
                name: "Map Specific Fields",
                type: "text",
                isPro: true,
                placeholder: "e.g., your-name, your-email (comma-separated field keys)"
              }
            ]
          }
        ]
      },
      {
        id: "actions",
        name: "Actions",
        actions: [
          {
            id: "delete_session",
            name: "Delete Chat Session",
            fields: [
              { id: "session_id", name: "Session ID", type: "text", placeholder: "Enter Session ID" }
            ]
          },
          {
            id: "get_chat_sessions",
            name: "Get Chat Sessions",
            description: "Retrieves chat sessions within a specified date range. Returns an array of sessions.",
            fields: [
              { id: "start_date", name: "Start Date", type: "text", placeholder: "e.g., YYYY-MM-DD or {yesterday}" },
              { id: "end_date", name: "End Date", type: "text", placeholder: "e.g., YYYY-MM-DD or {today}" }
            ]
          }
        ]
      }
    ]
  }
];


