"use client";

import React, { useState } from "react";
import { Search, Plus, Minus, GitBranch } from "lucide-react";
import { Input } from "./ui/Input";
import { appsMetadata as apps } from "../constants/appsMetadata";

const tools = [
  // { id: "t1", name: "HTTP Request", icon: "H", iconBg: "bg-slate-700" },
  // { id: "t2", name: "Code", icon: "<>", iconBg: "bg-emerald-600" },
  { id: "t3", name: "Webhook", icon: "W", iconBg: "bg-violet-600" },
  // { id: "t4", name: "Schedule", icon: "S", iconBg: "bg-amber-500" },
  // { id: "t5", name: "Filter", icon: "F", iconBg: "bg-cyan-600" },
  // { id: "t6", name: "Merge", icon: "M", iconBg: "bg-pink-500" },
  // { id: "t7", name: "Split", icon: "Sp", iconBg: "bg-teal-500" },
  // { id: "t8", name: "Wait", icon: "W", iconBg: "bg-gray-500" },
];

export function Sidebar({ onZoomIn, onZoomOut, onAddRouter, onAddNode }) {
  const [search, setSearch] = useState("");
  const [activeTab, setActiveTab] = useState("apps");
  const [draggedItem, setDraggedItem] = useState(null);

  const items = activeTab === "apps" ? apps : tools;
  const filteredItems = items.filter((item) =>
    item.name.toLowerCase().includes(search.toLowerCase())
  );

  const handleDragStart = (e, app) => {
    setDraggedItem(app.id);
    e.dataTransfer.setData("application/json", JSON.stringify(app));
    e.dataTransfer.effectAllowed = "copy";
  };

  const handleDragEnd = () => {
    setDraggedItem(null);
  };

  return (
    <div className="w-64 bg-sidebar border-r border-sidebar-border flex flex-col h-full">
      <div className="p-3">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9 bg-input border-border"
          />
        </div>
      </div>

      <div className="flex border-b border-sidebar-border">
        <button
          onClick={() => setActiveTab("apps")}
          className={`flex-1 py-2 text-sm font-medium transition-colors ${
            activeTab === "apps"
              ? "text-sidebar-foreground border-b-2 border-sidebar-primary"
              : "text-muted-foreground hover:text-sidebar-foreground"
          }`}
        >
          Apps
        </button>
        <button
          onClick={() => setActiveTab("tools")}
          className={`flex-1 py-2 text-sm font-medium transition-colors ${
            activeTab === "tools"
              ? "text-sidebar-foreground border-b-2 border-sidebar-primary"
              : "text-muted-foreground hover:text-sidebar-foreground"
          }`}
        >
          Tools
        </button>
      </div>


      <div className="flex-1 overflow-y-auto">
        <div
          onClick={onAddRouter}
          draggable
          onDragStart={(e) => handleDragStart(e, { id: "router", name: "Router", type: "router" })}
          onDragEnd={handleDragEnd}
          className={`w-full flex items-center gap-3 px-3 py-2.5 hover:bg-sidebar-accent transition-colors text-left border-b border-sidebar-border cursor-grab active:cursor-grabbing ${
            draggedItem === "router" ? "opacity-50" : ""
          }`}
        >
          <div className="w-8 h-8 rounded-lg bg-slate-500 flex items-center justify-center text-white">
            <GitBranch className="w-4 h-4" />
          </div>
          <span className="text-sm text-sidebar-foreground">Add Router</span>
        </div>

        {filteredItems.map((item) => (
          <div
            key={item.id}
            onClick={() => onAddNode(item)}
            draggable
            onDragStart={(e) => handleDragStart(e, item)}
            onDragEnd={handleDragEnd}
            className={`w-full flex items-center gap-3 px-3 py-2.5 hover:bg-sidebar-accent transition-colors text-left cursor-grab active:cursor-grabbing ${
              draggedItem === item.id ? "opacity-50" : ""
            }`}
          >
            <div
              className={`w-8 h-8 rounded-lg ${item.iconBg} flex items-center justify-center text-white text-xs font-semibold`}
            >
              {item.icon}
            </div>
            <span className="text-sm text-sidebar-foreground truncate">
              {item.name}
            </span>
          </div>
        ))}
      </div>

      <div className="p-3 border-t border-sidebar-border flex items-center gap-2">
        <button
          onClick={onZoomIn}
          className="p-2 hover:bg-sidebar-accent rounded-md transition-colors"
          title="Zoom In"
        >
          <Plus className="h-4 w-4 text-muted-foreground" />
        </button>
        <button
          onClick={onZoomOut}
          className="p-2 hover:bg-sidebar-accent rounded-md transition-colors"
          title="Zoom Out"
        >
          <Minus className="h-4 w-4 text-muted-foreground" />
        </button>
      </div>
    </div>
  );
}

export default Sidebar;
