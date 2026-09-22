"use client";

import React from "react";
import { ChevronLeft, Play, Settings, MoreVertical, ExternalLink } from "lucide-react";
import { Button } from "./ui/Button";

export function Header({ workflowName, onNameChange, onBack, onSave, onToggleLogs }) {
  return (
    <header className="h-14 bg-card border-b border-border flex items-center justify-between px-4">
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <input
            type="text"
            value={workflowName}
            onChange={(e) => onNameChange(e.target.value)}
            className="font-medium text-card-foreground bg-transparent border-none focus:outline-none focus:ring-1 focus:ring-primary rounded px-1"
            placeholder="Untitled Flow"
          />
        </div>

        <Button variant="outline" size="sm" className="gap-2 bg-transparent" onClick={onBack}>
          <ChevronLeft className="h-4 w-4" />
          Back
        </Button>

        <Button size="sm" className="gap-2 bg-primary text-primary-foreground hover:bg-primary/90" onClick={onSave}>
          <Play className="h-4 w-4" />
          Save Flow
        </Button>

        <div className="flex items-center gap-1">
          <div className="w-4 h-4 rounded-full border-2 border-muted-foreground/30" />
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Button variant="outline" size="sm" className="gap-2 bg-transparent">
          Try Pro
          <ExternalLink className="h-3 w-3" />
        </Button>

        <Button variant="outline" size="sm" className="gap-2 bg-transparent" onClick={onToggleLogs}>
          <Settings className="h-4 w-4" />
          Logs
        </Button>
      </div>
    </header>
  );
}

export default Header;
