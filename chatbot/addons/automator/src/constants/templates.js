import { appsMetadata as apps } from "./appsMetadata";

const findApp = (id) => apps.find(app => app.id === id);

export const templates = [
  {
    id: "wpforms-to-sheets",
    name: "WPForms to Google Sheets",
    description: "Automatically save WPForms submissions to a Google Spreadsheet.",
    icon: "F",
    iconBg: "bg-orange-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WPForms",
          subtitle: "Form submitted",
          position: { x: 100, y: 200 },
          icon: "F",
          iconBg: "bg-orange-500",
          actionNumber: 1,
          appData: findApp("wpforms")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Save Form Data)",
          position: { x: 500, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 2,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        {
          id: "conn-1",
          from: "node-trigger-1",
          to: "node-action-1",
          fromHandle: "right",
          toHandle: "left"
        }
      ]
    }
  },
  {
    id: "cf7-to-sheets",
    name: "Contact Form 7 to Google Sheets",
    description: "Automatically save Contact Form 7 submissions to a Google Spreadsheet.",
    icon: "C7",
    iconBg: "bg-black",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Contact Form 7",
          subtitle: "Form submitted",
          position: { x: 100, y: 200 },
          icon: "C7",
          iconBg: "bg-black",
          actionNumber: 1,
          appData: findApp("cf7")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Save Form Data)",
          position: { x: 500, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 2,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        {
          id: "conn-1",
          from: "node-trigger-1",
          to: "node-action-1",
          fromHandle: "right",
          toHandle: "left"
        }
      ]
    }
  },
  {
    id: "woocommerce-to-sheets-whatsapp",
    name: "WooCommerce Orders to Google Sheet + WhatsApp",
    description: "Save each WooCommerce order item to Google Sheets and notify the customer on WhatsApp.",
    isPro: true,
    icon: "WC",
    iconBg: "bg-purple-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WooCommerce",
          subtitle: "Order created",
          position: { x: 100, y: 200 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 1,
          appData: findApp("woocommerce")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Iterator",
          subtitle: "Loop through order items",
          position: { x: 340, y: 200 },
          icon: "↻",
          iconBg: "bg-amber-500",
          actionNumber: 2,
          appData: findApp("iterator")
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Order Item)",
          position: { x: 580, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 3,
          appData: findApp("google_sheets")
        },
        {
          id: "node-action-3",
          type: "action",
          title: "Iterator End",
          subtitle: "End loop",
          position: { x: 820, y: 200 },
          icon: "⏹",
          iconBg: "bg-amber-700",
          actionNumber: 4,
          appData: findApp("iterator_end")
        },
        {
          id: "node-action-4",
          type: "action",
          title: "WhatsApp",
          subtitle: "Send Message to Customer",
          position: { x: 1060, y: 200 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 5,
          appData: findApp("whatsapp")
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1", to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2", to: "node-action-3", fromHandle: "right", toHandle: "left" },
        { id: "conn-4", from: "node-action-3", to: "node-action-4", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "facebook-leads-to-sheets",
    name: "Facebook Lead Ads to Google Sheets",
    description: "Add a new row to Google Spreadsheets for every new lead from Facebook Lead Ads.",
    icon: "fb",
    iconBg: "bg-blue-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Facebook",
          subtitle: "Facebook Lead Ads",
          position: { x: 100, y: 200 },
          icon: "fb",
          iconBg: "bg-blue-600",
          actionNumber: 1,
          appData: findApp("facebook")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Lead Data)",
          position: { x: 500, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 2,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "fluent-forms-mailrefine-sheets",
    name: "Fluent Forms + MailRefine to Google Sheets",
    description: "Verify email submitted in Fluent Forms using MailRefine, then add the details to Google Sheets.",
    icon: "FF",
    iconBg: "bg-green-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Fluent Forms",
          subtitle: "Form submitted",
          position: { x: 100, y: 200 },
          icon: "FF",
          iconBg: "bg-green-600",
          actionNumber: 1,
          appData: findApp("fluent_forms")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "MailRefine",
          subtitle: "Verify Email",
          position: { x: 400, y: 200 },
          icon: "MR",
          iconBg: "bg-violet-600",
          actionNumber: 2,
          appData: findApp("mailrefine")
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Form Data)",
          position: { x: 700, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 3,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1", to: "node-action-2", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "cf7-to-fluentcrm-sheets",
    name: "Contact Form 7 to FluentCRM + Google Sheets",
    description: "Create a new contact in FluentCRM and add a row in Google Sheets on every Contact Form 7 submission.",
    icon: "C7",
    iconBg: "bg-black",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Contact Form 7",
          subtitle: "Form submitted",
          position: { x: 100, y: 200 },
          icon: "C7",
          iconBg: "bg-black",
          actionNumber: 1,
          appData: findApp("cf7")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "FluentCRM",
          subtitle: "Create/Update Contact",
          position: { x: 400, y: 200 },
          icon: "FC",
          iconBg: "bg-blue-700",
          actionNumber: 2,
          appData: findApp("fluentcrm")
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Form Data)",
          position: { x: 700, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 3,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1", to: "node-action-2", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "wpforms-mailrefine-filters-sheets",
    name: "WPForms + MailRefine + Filters to Google Sheets",
    description: "Validate the email from a WPForms submission with MailRefine, filter on the result, then add a row to Google Sheets.",
    icon: "F",
    iconBg: "bg-orange-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WPForms",
          subtitle: "Form submitted",
          position: { x: 100, y: 200 },
          icon: "F",
          iconBg: "bg-orange-500",
          actionNumber: 1,
          appData: findApp("wpforms")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "MailRefine",
          subtitle: "Verify Email",
          position: { x: 380, y: 200 },
          icon: "MR",
          iconBg: "bg-violet-600",
          actionNumber: 2,
          appData: findApp("mailrefine")
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Filters",
          subtitle: "Filter / Condition",
          position: { x: 660, y: 200 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 3,
          appData: findApp("filters")
        },
        {
          id: "node-action-3",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Form Data)",
          position: { x: 940, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 4,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1", to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2", to: "node-action-3", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "wp-post-to-social-media",
    name: "WP Post to FB, Insta, LinkedIn + Google Sheets + Telegram",
    description: "On WordPress post publish: generate AI-optimised posts for each platform, publish to Facebook, Instagram, LinkedIn and Telegram, then save post info to Google Sheets.",
    isPro: true,
    icon: "W",
    iconBg: "bg-blue-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WordPress",
          subtitle: "Post / Page published",
          position: { x: 100, y: 320 },
          icon: "W",
          iconBg: "bg-blue-600",
          actionNumber: 1,
          appData: findApp("wordpress")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "WordPress",
          subtitle: "Generate Social Posts (FB & Telegram)",
          position: { x: 380, y: 320 },
          icon: "W",
          iconBg: "bg-blue-600",
          actionNumber: 2,
          appData: findApp("wordpress")
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Facebook",
          subtitle: "Create Page Post",
          position: { x: 680, y: 80 },
          icon: "fb",
          iconBg: "bg-blue-600",
          actionNumber: 3,
          appData: findApp("facebook")
        },
        {
          id: "node-action-3",
          type: "action",
          title: "Telegram",
          subtitle: "Send Message",
          position: { x: 680, y: 240 },
          icon: "Tg",
          iconBg: "bg-blue-500",
          actionNumber: 4,
          appData: findApp("telegram")
        },
        {
          id: "node-action-4",
          type: "action",
          title: "WordPress",
          subtitle: "Generate Social Posts (Insta, WhatsApp, LinkedIn)",
          position: { x: 680, y: 400 },
          icon: "W",
          iconBg: "bg-blue-600",
          actionNumber: 5,
          appData: findApp("wordpress")
        },
        {
          id: "node-action-5",
          type: "action",
          title: "Instagram",
          subtitle: "Publish Photo",
          position: { x: 980, y: 240 },
          icon: "Ig",
          iconBg: "bg-pink-500",
          actionNumber: 6,
          appData: findApp("instagram")
        },
        {
          id: "node-action-6",
          type: "action",
          title: "LinkedIn",
          subtitle: "Create Text Post",
          position: { x: 980, y: 400 },
          icon: "In",
          iconBg: "bg-sky-600",
          actionNumber: 7,
          appData: findApp("linkedin")
        },
        {
          id: "node-action-7",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row (Post Info)",
          position: { x: 980, y: 560 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 8,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1",  to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",   to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-1",   to: "node-action-3", fromHandle: "right", toHandle: "left" },
        { id: "conn-4", from: "node-action-1",   to: "node-action-4", fromHandle: "right", toHandle: "left" },
        { id: "conn-5", from: "node-action-4",   to: "node-action-5", fromHandle: "right", toHandle: "left" },
        { id: "conn-6", from: "node-action-4",   to: "node-action-6", fromHandle: "right", toHandle: "left" },
        { id: "conn-7", from: "node-action-4",   to: "node-action-7", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "sub-workflow-email-validation",
    name: "Sub-Workflow: Extract Email",
    description: "A reusable sub-workflow that extracts an email address from text using regex and returns it.",
    icon: "WF",
    iconBg: "bg-teal-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Workflows",
          subtitle: "Sub-Workflow Trigger",
          position: { x: 100, y: 200 },
          icon: "WF",
          iconBg: "bg-teal-600",
          actionNumber: 1,
          appData: findApp("workflow")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Text Formatter",
          subtitle: "Extract Email",
          position: { x: 400, y: 200 },
          icon: "T",
          iconBg: "bg-teal-600",
          actionNumber: 2,
          appData: findApp("text_formatter"),
          data: {
            actionId: "extract_pattern",
            config: {
              text: "{raw_text}",
              pattern: "/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/i"
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Workflows",
          subtitle: "Return Values",
          position: { x: 700, y: 200 },
          icon: "WF",
          iconBg: "bg-teal-600",
          actionNumber: 3,
          appData: findApp("workflow"),
          data: {
            actionId: "return_values",
            config: {
              values: [
                { key: "email", value: "{node-action-1.output}" },
                { key: "is_valid", value: "true" }
              ]
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1", to: "node-action-2", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "parent-workflow-google-forms",
    name: "Google Forms to MailChimp via Sub-Workflow",
    description: "Calls a sub-workflow to extract email from form data, then adds the subscriber to MailChimp.",
    icon: "WF",
    iconBg: "bg-teal-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Webhook",
          subtitle: "Incoming Webhook",
          position: { x: 100, y: 200 },
          icon: "W",
          iconBg: "bg-violet-600",
          actionNumber: 1,
          appData: findApp("webhook")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Workflows",
          subtitle: "Call Sub-Workflow",
          position: { x: 400, y: 200 },
          icon: "WF",
          iconBg: "bg-teal-600",
          actionNumber: 2,
          appData: findApp("workflow"),
          data: {
            actionId: "call_sub_workflow",
            config: {
              inputs: [
                { key: "raw_text", value: "{contact_field}" }
              ]
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Filters",
          subtitle: "Only if Valid",
          position: { x: 700, y: 200 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 3,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{node-action-1.is_valid}",
              operator: "equals",
              value: "true"
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "MailChimp",
          subtitle: "Add Subscriber",
          position: { x: 1000, y: 200 },
          icon: "MC",
          iconBg: "bg-yellow-400",
          actionNumber: 4,
          appData: findApp("mailchimp"),
          data: {
            actionId: "add_subscriber",
            config: {
              email: "{node-action-1.email}"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1", to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2", to: "node-action-3", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "wc-review-followup-whatsapp",
    name: "Post-Purchase Review Follow-up (WhatsApp)",
    description: "5 days after an order is marked Completed, automatically send a WhatsApp template message asking the customer for a product review.",
    isPro: true,
    icon: "WA",
    iconBg: "bg-green-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WooCommerce",
          subtitle: "Order Status → Completed",
          position: { x: 100, y: 200 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 1,
          appData: findApp("woocommerce"),
          data: {
            actionId: "wc_order_status_change",
            config: {
              new_status: "completed"
            }
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Delay",
          subtitle: "Wait 5 Days",
          position: { x: 380, y: 200 },
          icon: "⏱",
          iconBg: "bg-slate-500",
          actionNumber: 2,
          appData: findApp("delay"),
          data: {
            actionId: "delay_execution",
            config: {
              delay_amount: "5",
              delay_unit: "days"
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "WhatsApp",
          subtitle: "Send Template Message",
          position: { x: 660, y: 200 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 3,
          appData: findApp("whatsapp"),
          data: {
            actionId: "wa_send_template",
            config: {
              phone_number_id: "",
              to: "{billing_phone}",
              template_name: "order_review_request",
              language_code: "en_US",
              body_parameters: "{billing_name}\n{order_id}"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "wc-order-completed-whatsapp-message",
    name: "Order Completed – WhatsApp Thank You + Tracking",
    description: "Instantly send a personalised WhatsApp message with order details and a thank-you note when an order is marked Completed.",
    isPro: true,
    icon: "WA",
    iconBg: "bg-green-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WooCommerce",
          subtitle: "Order Status → Completed",
          position: { x: 100, y: 200 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 1,
          appData: findApp("woocommerce"),
          data: {
            actionId: "wc_order_status_change",
            config: {
              new_status: "completed"
            }
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "WhatsApp",
          subtitle: "Send Thank You + Tracking Message",
          position: { x: 420, y: 200 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 2,
          appData: findApp("whatsapp"),
          data: {
            actionId: "wa_send_message",
            config: {
              phone_number_id: "",
              to: "{billing_phone}",
              message: "Hi {billing_name} 👋\n\nThank you for your order! 🎉\n\nYour order *#{order_id}* has been completed and is on its way.\n\n📦 *Order Summary:*\n{items}\n\n💰 *Total:* {total} {currency}\n\nIf you have any questions, just reply to this message. We appreciate your business! 🙏\n\n— Your Store Team"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "wc-order-completed-whatsapp-template",
    name: "Order Completed – WhatsApp Template Message (Tracking)",
    description: "Send a pre-approved WhatsApp template message with tracking info and a thank-you note the moment an order is completed. Best for high-volume stores requiring Meta compliance.",
    isPro: true,
    icon: "WA",
    iconBg: "bg-green-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WooCommerce",
          subtitle: "Order Status → Completed",
          position: { x: 100, y: 200 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 1,
          appData: findApp("woocommerce"),
          data: {
            actionId: "wc_order_status_change",
            config: {
              new_status: "completed"
            }
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "WhatsApp",
          subtitle: "Send Template: Order Completed",
          position: { x: 420, y: 200 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 2,
          appData: findApp("whatsapp"),
          data: {
            actionId: "wa_send_template",
            config: {
              phone_number_id: "",
              to: "{billing_phone}",
              template_name: "order_completed_notification",
              language_code: "en_US",
              body_parameters: "{billing_name}\n{order_id}\n{total} {currency}"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #1/6: Form-Based Lead to WhatsApp Alert ───────────────────────
  {
    id: "wpforms-lead-to-whatsapp-alert",
    name: "WPForms Lead to WhatsApp Alert + User Creation",
    description: "When a high-priority lead submits a WPForms form, instantly alert the sales team via WhatsApp and optionally create a new WordPress user.",
    isPro: true,
    icon: "F",
    iconBg: "bg-orange-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WPForms",
          subtitle: "Form submitted",
          position: { x: 100, y: 200 },
          icon: "F",
          iconBg: "bg-orange-500",
          actionNumber: 1,
          appData: findApp("wpforms"),
          data: {
            actionId: "wpforms_submit",
            config: { form_id: "" }
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Filters",
          subtitle: "High-Priority Lead Check",
          position: { x: 380, y: 200 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 2,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{service_type}",
              operator: "contains",
              value: "enterprise"
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "WhatsApp",
          subtitle: "Alert Sales Team",
          position: { x: 660, y: 120 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 3,
          appData: findApp("whatsapp"),
          data: {
            actionId: "wa_send_template",
            config: {
              phone_number_id: "",
              to: "{admin_whatsapp_number}",
              template_name: "new_lead_alert",
              language_code: "en_US",
              body_parameters: "{your-name}\n{your-email}\n{service_type}\n{budget}"
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "WordPress",
          subtitle: "Create New User",
          position: { x: 660, y: 300 },
          icon: "W",
          iconBg: "bg-blue-600",
          actionNumber: 4,
          appData: findApp("wordpress"),
          data: {
            actionId: "wp_create_user",
            config: {
              user_email: "{your-email}",
              user_login: "{your-email}",
              first_name: "{your-name}",
              role: "subscriber"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-1",  to: "node-action-3", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #4: External Inventory / Price Sync via JSON ──────────────────
  {
    id: "webhook-json-woocommerce-sync",
    name: "External Inventory & Price Sync via JSON Webhook",
    description: "Receive a supplier JSON payload via webhook, parse it with JSON Parser, and automatically update the matching WooCommerce product's price or stock.",
    isPro: true,
    icon: "{}",
    iconBg: "bg-yellow-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Webhook",
          subtitle: "Incoming Webhook",
          position: { x: 100, y: 200 },
          icon: "W",
          iconBg: "bg-violet-600",
          actionNumber: 1,
          appData: findApp("webhook"),
          data: {
            actionId: "incoming_webhook",
            config: {}
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "JSON Parser",
          subtitle: "Parse Supplier Payload",
          position: { x: 380, y: 200 },
          icon: "{}",
          iconBg: "bg-yellow-600",
          actionNumber: 2,
          appData: findApp("json_parser"),
          data: {
            actionId: "parse_json_text",
            config: {
              json_text: "{body}"
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "WooCommerce",
          subtitle: "Update Product Price / Stock",
          position: { x: 660, y: 200 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 3,
          appData: findApp("woocommerce"),
          data: {
            actionId: "wc_update_product",
            config: {
              product_id: "{product_id}",
              regular_price: "{price}",
              stock_quantity: "{stock}"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #9: Scrape Content to WordPress Post ──────────────────────────
  {
    id: "scrape-to-wp-post",
    name: "Scrape Webpage & Publish AI-Rewritten WordPress Post",
    description: "Triggered by an incoming webhook with a URL, fetches and parses the webpage, rewrites the content with AI, then saves it as a WordPress draft post.",
    isPro: true,
    icon: "🌐",
    iconBg: "bg-teal-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Webhook",
          subtitle: "Incoming Webhook (with URL)",
          position: { x: 100, y: 200 },
          icon: "W",
          iconBg: "bg-violet-600",
          actionNumber: 1,
          appData: findApp("webhook"),
          data: {
            actionId: "incoming_webhook",
            config: {}
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Webpage Parser",
          subtitle: "Fetch & Parse URL",
          position: { x: 380, y: 200 },
          icon: "🌐",
          iconBg: "bg-teal-500",
          actionNumber: 2,
          appData: findApp("webpage_parser"),
          data: {
            actionId: "parse_webpage_url",
            config: {
              webpage_url: "{url}"
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "OpenAI",
          subtitle: "Rewrite Content",
          position: { x: 660, y: 200 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 3,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "You are a professional content writer. Rewrite the following article in your own words, ensuring it is original, SEO-friendly, and engaging. Return a JSON object with two keys: \"title\" (a compelling post title) and \"content\" (the full HTML body of the rewritten article).\n\nOriginal Title: {node-action-1.title}\n\nOriginal Content:\n{node-action-1.content}",
              max_tokens: 2000,
              temperature: 0.7
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "WordPress",
          subtitle: "Create Draft Post",
          position: { x: 940, y: 200 },
          icon: "W",
          iconBg: "bg-blue-600",
          actionNumber: 4,
          appData: findApp("wordpress"),
          data: {
            actionId: "wp_create_post",
            config: {
              post_title: "{node-action-2.title}",
              post_content: "{node-action-2.content}",
              post_status: "draft"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2",  to: "node-action-3", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #10: Smart Lead Qualifier & CRM Router ────────────────────────
  {
    id: "smart-lead-qualifier-crm-router",
    name: "Smart Lead Qualifier & CRM Router (AI)",
    description: "WPForms lead submission is analysed by AI to determine lead value. High-value leads get a VIP tag in FluentCRM and an instant WhatsApp alert; low-value leads are added to a nurture sequence.",
    isPro: true,
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WPForms",
          subtitle: "Form submitted",
          position: { x: 100, y: 260 },
          icon: "F",
          iconBg: "bg-orange-500",
          actionNumber: 1,
          appData: findApp("wpforms"),
          data: {
            actionId: "wpforms_submit",
            config: { form_id: "" }
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "OpenAI",
          subtitle: "Qualify Lead Intent & Value",
          position: { x: 380, y: 260 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 2,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "Analyse this lead submission and respond with ONLY a JSON object (no markdown) containing two keys:\n- \"tier\": either \"high\" or \"low\"\n- \"budget\": the numeric budget mentioned (0 if not stated)\n\nLead Message: {message}\nBudget Field: {budget}\nService: {service_type}",
              max_tokens: 100,
              temperature: 0.2
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Filters",
          subtitle: "High-Value Lead?",
          position: { x: 660, y: 140 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 3,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{node-action-1.tier}",
              operator: "equals",
              value: "high"
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "FluentCRM",
          subtitle: "Add VIP Tag & Create Contact",
          position: { x: 940, y: 80 },
          icon: "FC",
          iconBg: "bg-blue-700",
          actionNumber: 4,
          appData: findApp("fluentcrm"),
          data: {
            actionId: "create_contact",
            config: {
              email: "{your-email}",
              first_name: "{your-name}",
              status: "subscribed",
              tags: "VIP, high-value"
            }
          }
        },
        {
          id: "node-action-4",
          type: "action",
          title: "WhatsApp",
          subtitle: "Alert Sales Team (VIP Lead)",
          position: { x: 940, y: 220 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 5,
          appData: findApp("whatsapp"),
          data: {
            actionId: "wa_send_message",
            config: {
              phone_number_id: "",
              to: "{admin_whatsapp_number}",
              message: "🔥 *VIP Lead Alert!*\n\nName: {your-name}\nEmail: {your-email}\nService: {service_type}\nBudget: {node-action-1.budget}\n\nRespond within 1 hour for best conversion!"
            }
          }
        },
        {
          id: "node-action-5",
          type: "action",
          title: "Filters",
          subtitle: "Low-Value Lead?",
          position: { x: 660, y: 380 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 6,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{node-action-1.tier}",
              operator: "equals",
              value: "low"
            }
          }
        },
        {
          id: "node-action-6",
          type: "action",
          title: "FluentCRM",
          subtitle: "Add to Nurture Sequence",
          position: { x: 940, y: 380 },
          icon: "FC",
          iconBg: "bg-blue-700",
          actionNumber: 7,
          appData: findApp("fluentcrm"),
          data: {
            actionId: "create_contact",
            config: {
              email: "{your-email}",
              first_name: "{your-name}",
              status: "subscribed",
              tags: "nurture, follow-up"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2",  to: "node-action-3", fromHandle: "right", toHandle: "left" },
        { id: "conn-4", from: "node-action-2",  to: "node-action-4", fromHandle: "right", toHandle: "left" },
        { id: "conn-5", from: "node-action-1",  to: "node-action-5", fromHandle: "right", toHandle: "left" },
        { id: "conn-6", from: "node-action-5",  to: "node-action-6", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #11: Multilingual Customer Support Auto-Responder ──────────────
  {
    id: "multilingual-support-auto-responder",
    name: "Multilingual Support Auto-Responder (AI)",
    description: "Contact Form 7 submission is analysed by AI which detects the language, translates it to English for the admin, and drafts a contextual reply in the customer's original language — sent via email and WhatsApp.",
    isPro: true,
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Contact Form 7",
          subtitle: "Form submitted",
          position: { x: 100, y: 260 },
          icon: "C7",
          iconBg: "bg-black",
          actionNumber: 1,
          appData: findApp("cf7"),
          data: {
            actionId: "cf7_submit",
            config: { form_id: "" }
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "OpenAI",
          subtitle: "Detect Language & Translate",
          position: { x: 380, y: 260 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 2,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "You are a multilingual support assistant. Given the customer message below, respond with ONLY a JSON object (no markdown) with these keys:\n- \"detected_language\": the full language name (e.g. \"Spanish\")\n- \"english_translation\": the full message translated to English\n- \"customer_reply\": a friendly, helpful support reply written in the SAME language as the original message\n\nCustomer Name: {your-name}\nCustomer Message: {your-message}",
              max_tokens: 800,
              temperature: 0.5
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Mail",
          subtitle: "Notify Admin (English Translation)",
          position: { x: 660, y: 140 },
          icon: "M",
          iconBg: "bg-gray-700",
          actionNumber: 3,
          appData: findApp("mail"),
          data: {
            actionId: "send_email",
            config: {
              to_email: "{admin_email}",
              subject: "New Support Request ({node-action-1.detected_language}) from {your-name}",
              body: "<h3>New Multilingual Support Request</h3><p><strong>Customer:</strong> {your-name} ({your-email})</p><p><strong>Language:</strong> {node-action-1.detected_language}</p><p><strong>Original Message:</strong><br>{your-message}</p><p><strong>English Translation:</strong><br>{node-action-1.english_translation}</p>",
              is_html: true
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "WhatsApp",
          subtitle: "Send Reply to Customer",
          position: { x: 660, y: 320 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 4,
          appData: findApp("whatsapp"),
          data: {
            actionId: "wa_send_message",
            config: {
              phone_number_id: "",
              to: "{your-phone}",
              message: "{node-action-1.customer_reply}"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-1",  to: "node-action-3", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #12: Automated Support-to-Knowledge Base ──────────────────────
  {
    id: "support-to-knowledge-base",
    name: "Automated Support → Knowledge Base Post (AI)",
    description: "When a FluentCRM contact note is updated, AI anonymises the data, extracts the core problem and solution, and creates a clean FAQ draft as a WordPress post.",
    isPro: true,
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Webhook",
          subtitle: "FluentCRM Note Updated (Webhook)",
          position: { x: 100, y: 200 },
          icon: "W",
          iconBg: "bg-violet-600",
          actionNumber: 1,
          appData: findApp("webhook"),
          data: {
            actionId: "incoming_webhook",
            config: {}
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "OpenAI",
          subtitle: "Anonymise & Extract FAQ",
          position: { x: 380, y: 200 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 2,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "You are a technical writer. Given the support interaction below, respond with ONLY a JSON object (no markdown) with these keys:\n- \"faq_title\": a concise FAQ-style question (the core problem)\n- \"faq_content\": a clean HTML answer (the solution), fully anonymised with no personal data\n- \"should_publish\": true if the resolution quality is good enough to publish, false otherwise\n\nSupport Note:\n{note_content}",
              max_tokens: 1000,
              temperature: 0.4
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Filters",
          subtitle: "Only if Quality is Good",
          position: { x: 660, y: 200 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 3,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{node-action-1.should_publish}",
              operator: "is_true",
              value: ""
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "WordPress",
          subtitle: "Create FAQ Draft Post",
          position: { x: 940, y: 200 },
          icon: "W",
          iconBg: "bg-blue-600",
          actionNumber: 4,
          appData: findApp("wordpress"),
          data: {
            actionId: "wp_create_post",
            config: {
              post_title: "{node-action-1.faq_title}",
              post_content: "{node-action-1.faq_content}",
              post_status: "draft",
              categories: "FAQ, Knowledge Base"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2",  to: "node-action-3", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #13: Dynamic Review Sentiment Shield ───────────────────────────
  {
    id: "wc-review-sentiment-shield",
    name: "WooCommerce Review Sentiment Shield (AI)",
    description: "When a WooCommerce product review is created, AI analyses the sentiment. Negative reviews trigger a personalised apology via WhatsApp and a discount coupon email; positive reviews are approved automatically.",
    isPro: true,
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Webhook",
          subtitle: "WooCommerce Product Review Created",
          position: { x: 100, y: 260 },
          icon: "W",
          iconBg: "bg-violet-600",
          actionNumber: 1,
          appData: findApp("webhook"),
          data: {
            actionId: "incoming_webhook",
            config: {}
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "OpenAI",
          subtitle: "Analyse Review Sentiment",
          position: { x: 380, y: 260 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 2,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "Analyse this product review and respond with ONLY a JSON object (no markdown) with these keys:\n- \"sentiment\": either \"positive\" or \"negative\"\n- \"complaint_type\": if negative, either \"shipping\" or \"product_quality\" or \"other\"; if positive use \"none\"\n- \"apology_message\": if negative, a personalised, empathetic WhatsApp message (2-3 sentences) addressing the specific complaint and offering a 10% discount code SAVE10\n\nReview Rating: {rating}\nReview Text: {review_content}\nProduct: {product_name}",
              max_tokens: 400,
              temperature: 0.6
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Filters",
          subtitle: "Is Negative Review?",
          position: { x: 660, y: 160 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 3,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{node-action-1.sentiment}",
              operator: "equals",
              value: "negative"
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "WhatsApp",
          subtitle: "Send Apology + Coupon",
          position: { x: 940, y: 100 },
          icon: "WA",
          iconBg: "bg-green-500",
          actionNumber: 4,
          appData: findApp("whatsapp"),
          data: {
            actionId: "wa_send_message",
            config: {
              phone_number_id: "",
              to: "{reviewer_phone}",
              message: "{node-action-1.apology_message}"
            }
          }
        },
        {
          id: "node-action-4",
          type: "action",
          title: "Mail",
          subtitle: "Email Discount Coupon",
          position: { x: 940, y: 240 },
          icon: "M",
          iconBg: "bg-gray-700",
          actionNumber: 5,
          appData: findApp("mail"),
          data: {
            actionId: "send_email",
            config: {
              to_email: "{reviewer_email}",
              subject: "We're sorry — here's 10% off your next order",
              body: "<p>Dear {reviewer_name},</p><p>We sincerely apologise for your experience with <strong>{product_name}</strong>. As a token of our appreciation for your feedback, please use code <strong>SAVE10</strong> for 10% off your next order.</p><p>Thank you for helping us improve!</p>",
              is_html: true
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2",  to: "node-action-3", fromHandle: "right", toHandle: "left" },
        { id: "conn-4", from: "node-action-2",  to: "node-action-4", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #14: Price Monitor & Updater ──────────────────────────────────
  {
    id: "price-monitor-updater",
    name: "AI Price Monitor & WooCommerce Auto-Updater",
    description: "Receive supplier pricing data via webhook, let AI compare it against your current margins, and automatically update WooCommerce sale prices or trigger a low-margin alert.",
    isPro: true,
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Webhook",
          subtitle: "Supplier Price Feed (Incoming)",
          position: { x: 100, y: 200 },
          icon: "W",
          iconBg: "bg-violet-600",
          actionNumber: 1,
          appData: findApp("webhook"),
          data: {
            actionId: "incoming_webhook",
            config: {}
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "JSON Parser",
          subtitle: "Parse Supplier Feed",
          position: { x: 380, y: 200 },
          icon: "{}",
          iconBg: "bg-yellow-600",
          actionNumber: 2,
          appData: findApp("json_parser"),
          data: {
            actionId: "parse_json_text",
            config: {
              json_text: "{body}"
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "OpenAI",
          subtitle: "Evaluate Pricing Decision",
          position: { x: 660, y: 200 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 3,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "You are a pricing strategist. Given the supplier cost and the current store price, respond with ONLY a JSON object (no markdown) with these keys:\n- \"action\": either \"update_price\", \"low_margin_alert\", or \"no_change\"\n- \"new_sale_price\": the recommended sale price (number, 0 if no change)\n- \"margin_percent\": the calculated profit margin percentage\n- \"reason\": a one-sentence explanation\n\nSupplier Cost: {node-action-1.supplier_cost}\nCurrent Store Price: {node-action-1.current_price}\nTarget Margin: 30%",
              max_tokens: 200,
              temperature: 0.2
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "Filters",
          subtitle: "Should Update Price?",
          position: { x: 940, y: 120 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 4,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{node-action-2.action}",
              operator: "equals",
              value: "update_price"
            }
          }
        },
        {
          id: "node-action-4",
          type: "action",
          title: "WooCommerce",
          subtitle: "Update Product Sale Price",
          position: { x: 1220, y: 80 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 5,
          appData: findApp("woocommerce"),
          data: {
            actionId: "wc_update_product",
            config: {
              product_id: "{node-action-1.product_id}",
              sale_price: "{node-action-2.new_sale_price}"
            }
          }
        },
        {
          id: "node-action-5",
          type: "action",
          title: "Filters",
          subtitle: "Low Margin Alert?",
          position: { x: 940, y: 300 },
          icon: "FT",
          iconBg: "bg-yellow-500",
          actionNumber: 6,
          appData: findApp("filters"),
          data: {
            actionId: "filter_condition",
            config: {
              field: "{node-action-2.action}",
              operator: "equals",
              value: "low_margin_alert"
            }
          }
        },
        {
          id: "node-action-6",
          type: "action",
          title: "Mail",
          subtitle: "Send Margin Warning Email",
          position: { x: 1220, y: 300 },
          icon: "M",
          iconBg: "bg-gray-700",
          actionNumber: 7,
          appData: findApp("mail"),
          data: {
            actionId: "send_email",
            config: {
              to_email: "{admin_email}",
              subject: "⚠️ Low Margin Alert: Product #{node-action-1.product_id}",
              body: "<h3>Inventory Margin Warning</h3><p>Product <strong>#{node-action-1.product_id}</strong> has dropped below the 30% margin threshold.</p><p><strong>Supplier Cost:</strong> {node-action-1.supplier_cost}<br><strong>Current Price:</strong> {node-action-1.current_price}<br><strong>Margin:</strong> {node-action-2.margin_percent}%</p><p><strong>Recommendation:</strong> {node-action-2.reason}</p>",
              is_html: true
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-2",  to: "node-action-3", fromHandle: "right", toHandle: "left" },
        { id: "conn-4", from: "node-action-3",  to: "node-action-4", fromHandle: "right", toHandle: "left" },
        { id: "conn-5", from: "node-action-2",  to: "node-action-5", fromHandle: "right", toHandle: "left" },
        { id: "conn-6", from: "node-action-5",  to: "node-action-6", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #15: Automated Press & News Aggregator to Blog ────────────────
  {
    id: "news-aggregator-to-blog",
    name: "Automated News Aggregator → AI Blog Post",
    description: "Triggered by an incoming webhook with article URLs, AI fetches and blends multiple news sources into a single original blog post saved as a WordPress draft ready for editorial review.",
    isPro: true,
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "Webhook",
          subtitle: "Incoming Webhook (Article URLs)",
          position: { x: 100, y: 200 },
          icon: "W",
          iconBg: "bg-violet-600",
          actionNumber: 1,
          appData: findApp("webhook"),
          data: {
            actionId: "incoming_webhook",
            config: {}
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Webpage Parser",
          subtitle: "Fetch First Article",
          position: { x: 380, y: 120 },
          icon: "🌐",
          iconBg: "bg-teal-500",
          actionNumber: 2,
          appData: findApp("webpage_parser"),
          data: {
            actionId: "parse_webpage_url",
            config: {
              webpage_url: "{url_1}"
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Webpage Parser",
          subtitle: "Fetch Second Article",
          position: { x: 380, y: 280 },
          icon: "🌐",
          iconBg: "bg-teal-500",
          actionNumber: 3,
          appData: findApp("webpage_parser"),
          data: {
            actionId: "parse_webpage_url",
            config: {
              webpage_url: "{url_2}"
            }
          }
        },
        {
          id: "node-action-3",
          type: "action",
          title: "OpenAI",
          subtitle: "Blend & Write Original Article",
          position: { x: 700, y: 200 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 4,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o",
              prompt: "You are a professional journalist and content writer. Read the following articles and blend them into a single, original, engaging blog post. Do NOT copy — summarise the key takeaways and present them in a fresh perspective. Respond with ONLY a JSON object (no markdown) with two keys:\n- \"title\": a compelling, SEO-friendly blog post title\n- \"content\": the full HTML body of the article (600-900 words, with proper headings)\n\nArticle 1 Title: {node-action-1.title}\nArticle 1 Content: {node-action-1.content}\n\nArticle 2 Title: {node-action-2.title}\nArticle 2 Content: {node-action-2.content}",
              max_tokens: 2500,
              temperature: 0.75
            }
          }
        },
        {
          id: "node-action-4",
          type: "action",
          title: "WordPress",
          subtitle: "Save as Draft Post",
          position: { x: 980, y: 200 },
          icon: "W",
          iconBg: "bg-blue-600",
          actionNumber: 5,
          appData: findApp("wordpress"),
          data: {
            actionId: "wp_create_post",
            config: {
              post_title: "{node-action-3.title}",
              post_content: "{node-action-3.content}",
              post_status: "draft",
              categories: "News, Industry Updates"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-trigger-1", to: "node-action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "node-action-1",  to: "node-action-3", fromHandle: "right", toHandle: "left" },
        { id: "conn-4", from: "node-action-2",  to: "node-action-3", fromHandle: "right", toHandle: "left" },
        { id: "conn-5", from: "node-action-3",  to: "node-action-4", fromHandle: "right", toHandle: "left" }
      ]
    }
  },

  // ─── Template #16: AI-Driven Product Description Enhancer ───────────────────
  {
    id: "ai-product-description-enhancer",
    name: "AI Product Description Enhancer (WooCommerce)",
    description: "When a WooCommerce product is created or updated, AI reads the basic attributes and generates an SEO-optimised meta title, meta description, and a compelling long-form product description.",
    isPro: true,
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WooCommerce",
          subtitle: "Product Added",
          position: { x: 100, y: 200 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 1,
          appData: findApp("woocommerce"),
          data: {
            actionId: "wc_product_added",
            config: {}
          }
        },
        {
          id: "node-action-1",
          type: "action",
          title: "OpenAI",
          subtitle: "Generate SEO Content",
          position: { x: 380, y: 200 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 2,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "You are an expert eCommerce copywriter and SEO specialist. Given the product details below, respond with ONLY a JSON object (no markdown) with these keys:\n- \"meta_title\": an SEO-optimised meta title (max 60 characters)\n- \"meta_description\": a compelling meta description (max 160 characters)\n- \"long_description\": a full HTML product description (300-500 words) with bullet points for key features, benefits, and a call-to-action\n\nProduct Name: {product_name}\nProduct Category: {product_category}\nProduct Price: {product_price}\nExisting Short Description: {product_short_description}\nProduct Attributes: {product_attributes}",
              max_tokens: 1200,
              temperature: 0.65
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "WooCommerce",
          subtitle: "Update Product Description & SEO",
          position: { x: 700, y: 200 },
          icon: "WC",
          iconBg: "bg-purple-600",
          actionNumber: 3,
          appData: findApp("woocommerce"),
          data: {
            actionId: "wc_update_product",
            config: {
              product_id: "{product_id}",
              description: "{node-action-1.long_description}",
              meta_title: "{node-action-1.meta_title}",
              meta_description: "{node-action-1.meta_description}"
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1",  to: "node-action-2", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "wpbot-to-sheets",
    name: "WPBot Session to Google Sheet",
    description: "Automatically save WPBot chat session details (Name, Email, Phone) to a Google Sheet.",
    icon: "WB",
    iconBg: "bg-blue-500",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WPBot",
          subtitle: "Chat Session Saved",
          position: { x: 100, y: 200 },
          icon: "WB",
          iconBg: "bg-blue-500",
          actionNumber: 1,
          appData: findApp("wpbot")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row",
          position: { x: 500, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 2,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        {
          id: "conn-1",
          from: "node-trigger-1",
          to: "node-action-1",
          fromHandle: "right",
          toHandle: "left"
        }
      ]
    }
  },
  {
    id: "wpbot-ai-sheets",
    name: "WPBot AI Email Extractor to Google Sheets",
    description: "Chat sessions are sent to AI for analysis to extract email addresses, which are then saved in a Google Sheet.",
    icon: "OA",
    iconBg: "bg-emerald-600",
    workflow_data: {
      nodes: [
        {
          id: "node-trigger-1",
          type: "trigger",
          title: "WPBot",
          subtitle: "Chat Session Saved",
          position: { x: 100, y: 200 },
          icon: "WB",
          iconBg: "bg-blue-500",
          actionNumber: 1,
          appData: findApp("wpbot")
        },
        {
          id: "node-action-1",
          type: "action",
          title: "OpenAI",
          subtitle: "Extract Emails",
          position: { x: 420, y: 200 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 2,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "Extract any email addresses from the following chat transcript. If none are found, return 'No Email'. Return ONLY the email addresses comma-separated, with no other text.\n\nTranscript: {{wpbot.wpbot_chat_session_saved.conversation}}",
              max_tokens: 100,
              temperature: 0.1
            }
          }
        },
        {
          id: "node-action-2",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add Row",
          position: { x: 740, y: 200 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 3,
          appData: findApp("google_sheets")
        }
      ],
      connections: [
        { id: "conn-1", from: "node-trigger-1", to: "node-action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "node-action-1", to: "node-action-2", fromHandle: "right", toHandle: "left" }
      ]
    }
  },
  {
    id: "conversational-form-to-sheet-ai-email",
    name: "Conversational Form to Sheet, AI & Email",
    description: "Saves conversational form data to Google Sheets, generates an AI summary, and emails the response.",
    icon: "F",
    iconBg: "bg-orange-500",
    workflow_data: {
      nodes: [
        {
          id: "trigger-1",
          type: "trigger",
          title: "Chatbot Form Builder",
          subtitle: "Conversational Forms Submitted",
          position: { x: 100, y: 100 },
          icon: "WB",
          iconBg: "bg-blue-500",
          actionNumber: 1,
          appData: findApp("wpbot"),
          data: {
            actionId: "conversationalforms_submit",
            config: {}
          }
        },
        {
          id: "action-1",
          type: "action",
          title: "Google Sheets",
          subtitle: "Add row to Google Sheet",
          position: { x: 400, y: 100 },
          icon: "GS",
          iconBg: "bg-green-600",
          actionNumber: 2,
          appData: findApp("google_sheets"),
          data: {
            actionId: "add_row",
            config: {
              sheet_id: "your_google_sheet_id_here",
              worksheet: "Sheet1",
              row_data: [
                "{{wpbot.conversationalforms_submit.name}}",
                "{{wpbot.conversationalforms_submit.email}}"
              ]
            }
          }
        },
        {
          id: "action-2",
          type: "action",
          title: "OpenAI",
          subtitle: "Generate Summary",
          position: { x: 700, y: 100 },
          icon: "OA",
          iconBg: "bg-emerald-600",
          actionNumber: 3,
          appData: findApp("openai"),
          data: {
            actionId: "chat_completion",
            config: {
              model: "gpt-4o-mini",
              prompt: "Summarize the following form submission: Name: {{wpbot.conversationalforms_submit.name}}, Email: {{wpbot.conversationalforms_submit.email}}",
              max_tokens: "150",
              temperature: "0.7"
            }
          }
        },
        {
          id: "action-3",
          type: "action",
          title: "Mail",
          subtitle: "Send Email Response",
          position: { x: 1000, y: 100 },
          icon: "M",
          iconBg: "bg-gray-700",
          actionNumber: 4,
          appData: findApp("mail"),
          data: {
            actionId: "send_email",
            config: {
              to_email: "{{wpbot.conversationalforms_submit.email}}",
              subject: "Thank you for your submission",
              body: "Hello {{wpbot.conversationalforms_submit.name}},\n\nHere is a summary of your submission:\n{{openai.chat_completion.response}}",
              is_html: false
            }
          }
        }
      ],
      connections: [
        { id: "conn-1", from: "trigger-1", to: "action-1", fromHandle: "right", toHandle: "left" },
        { id: "conn-2", from: "action-1", to: "action-2", fromHandle: "right", toHandle: "left" },
        { id: "conn-3", from: "action-2", to: "action-3", fromHandle: "right", toHandle: "left" }
      ]
    }
  }
];
