"use client";

import React from "react";
import { X } from "lucide-react";

export function WorkflowNode({
  node,
  scale,
  onDragStart,
  onConnectionStart,
  onConnectionEnd,
  onDelete,
  isSelected,
  onClick,
}) {
  const getNodeTypeLabel = () => {
    switch (node.type) {
      case "trigger":
        return { label: "Trigger", color: "text-emerald-600" };
      case "router":
        return { label: "Router", color: "text-slate-500" };
      case "action":
        return { label: "Action", color: "text-primary" };
      default:
        return { label: "Action", color: "text-primary" };
    }
  };

  const typeInfo = getNodeTypeLabel();

  const handleMouseDownOnHandle = (e, handle) => {
    e.stopPropagation();
    onConnectionStart(node.id, handle);
  };

  const handleMouseUpOnHandle = (e, handle) => {
    e.stopPropagation();
    onConnectionEnd(node.id, handle);
  };

  return (
    <div
      className="absolute select-none group"
      style={{
        left: node.position.x * scale,
        top: node.position.y * scale,
        transform: `scale(${scale})`,
        transformOrigin: "top left",
      }}
    >
      {/* Type Label */}
      <div className="flex items-center gap-2 mb-1">
        <span className={`text-xs font-medium ${typeInfo.color}`}>
          {typeInfo.label}
        </span>
        {node.actionNumber !== undefined && (
          <span className="text-xs text-muted-foreground">
            {node.actionNumber}
          </span>
        )}
      </div>

      {/* Node Card */}
      <div className="relative">
        {/* Left Handle - for incoming connections */}
        <div
          className="absolute left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 rounded-full border-2 border-foreground/30 bg-card z-10 cursor-crosshair hover:border-primary hover:bg-primary/20 transition-colors"
          onMouseDown={(e) => handleMouseDownOnHandle(e, "left")}
          onMouseUp={(e) => handleMouseUpOnHandle(e, "left")}
        />

        {/* Delete Button */}
        <button
          onClick={(e) => {
            e.stopPropagation();
            onDelete(node.id);
          }}
          className="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-destructive text-destructive-foreground flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-20 hover:bg-destructive/90"
        >
          <X className="w-3 h-3" />
        </button>

        {/* Node Content */}
        <div
          className={`flex items-center gap-3 px-4 py-3 bg-card rounded-lg border shadow-sm min-w-[180px] cursor-move transition-all ${
            isSelected ? "border-primary ring-2 ring-primary/20" : "border-border"
          } ${
            node.hasError ? "ring-2 ring-destructive" : ""
          }`}
          onMouseDown={(e) => onDragStart(e, node.id)}
          onClick={(e) => {
            e.stopPropagation();
            onClick();
          }}
        >
          <div
            className={`w-9 h-9 rounded-lg ${node.iconBg || 'bg-primary'} flex items-center justify-center text-white text-sm font-semibold flex-shrink-0`}
          >
            {node.icon || '⚙️'}
          </div>
          <div className="flex-1 min-w-0">
            <div className="text-sm font-medium text-card-foreground truncate">
              {node.title || node.label}
            </div>
            <div className="text-xs text-muted-foreground truncate">
              {node.subtitle || node.description || "Configure action"}
            </div>
          </div>
        </div>

        {/* Right Handle - for outgoing connections */}
        <div
          className="absolute right-0 top-1/2 translate-x-1/2 -translate-y-1/2 w-4 h-4 rounded-full border-2 border-foreground/30 bg-card z-10 cursor-crosshair hover:border-primary hover:bg-primary/20 transition-colors"
          onMouseDown={(e) => handleMouseDownOnHandle(e, "right")}
          onMouseUp={(e) => handleMouseUpOnHandle(e, "right")}
        />

        {/* Error Badge */}
        {node.hasError && (
          <div className="absolute -top-2 -left-2 w-5 h-5 rounded-full bg-destructive flex items-center justify-center text-destructive-foreground text-xs font-bold z-20">
            1
          </div>
        )}
      </div>
    </div>
  );
}

export default WorkflowNode;
