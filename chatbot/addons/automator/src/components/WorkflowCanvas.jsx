"use client";

import React, { useState, useCallback, useRef } from "react";
import { WorkflowNode } from "./WorkflowNode";
import { Connections } from "./Connections";

export function WorkflowCanvas({
  scale,
  setScale,
  nodes,
  setNodes,
  connections,
  setConnections,
  selectedNodeId,
  onNodeClick,
}) {
  const [isPanning, setIsPanning] = useState(false);
  const [panOffset, setPanOffset] = useState({ x: 0, y: 0 });
  const [draggedNode, setDraggedNode] = useState(null);
  const [dragOffset, setDragOffset] = useState({ x: 0, y: 0 });
  const [connectingFrom, setConnectingFrom] = useState(null);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });
  const canvasRef = useRef(null);

  const handleMouseDown = useCallback(
    (e) => {
      if (e.target === canvasRef.current || e.target.closest('[data-grid]')) {
        setIsPanning(true);
      }
    },
    []
  );

  const handleNodeDragStart = useCallback(
    (e, nodeId) => {
      e.stopPropagation();
      setDraggedNode(nodeId);

      const node = nodes.find((n) => n.id === nodeId);
      if (node) {
        setDragOffset({
          x: e.clientX - (node.position.x * scale + panOffset.x),
          y: e.clientY - (node.position.y * scale + panOffset.y),
        });
      }
    },
    [nodes, scale, panOffset]
  );

  const handleConnectionStart = useCallback(
    (nodeId, handle) => {
      setConnectingFrom({ nodeId, handle });
    },
    []
  );

  const handleConnectionEnd = useCallback(
    (nodeId, handle) => {
      if (connectingFrom && connectingFrom.nodeId !== nodeId) {
        const newConnection = {
          id: `conn-${Date.now()}`,
          from: connectingFrom.handle === "right" ? connectingFrom.nodeId : nodeId,
          to: connectingFrom.handle === "right" ? nodeId : connectingFrom.nodeId,
          fromHandle: "right",
          toHandle: "left",
        };
        setConnections((prev) => [...prev, newConnection]);
      }
      setConnectingFrom(null);
    },
    [connectingFrom, setConnections]
  );

  const handleMouseMove = useCallback(
    (e) => {
      const rect = canvasRef.current?.getBoundingClientRect();
      if (rect) {
        setMousePos({
          x: e.clientX - rect.left,
          y: e.clientY - rect.top,
        });
      }

      if (isPanning) {
        setPanOffset((prev) => ({
          x: prev.x + e.movementX,
          y: prev.y + e.movementY,
        }));
      } else if (draggedNode) {
        const newX = (e.clientX - dragOffset.x - panOffset.x) / scale;
        const newY = (e.clientY - dragOffset.y - panOffset.y) / scale;

        setNodes((prev) =>
          prev.map((node) =>
            node.id === draggedNode
              ? { ...node, position: { x: newX, y: newY } }
              : node
          )
        );
      }
    },
    [isPanning, draggedNode, dragOffset, scale, panOffset, setNodes]
  );

  const handleMouseUp = useCallback(() => {
    setIsPanning(false);
    setDraggedNode(null);
    setConnectingFrom(null);
  }, []);

  const handleDrop = useCallback(
    (e) => {
      e.preventDefault();
      const dataStr = e.dataTransfer.getData("application/json");
      if (!dataStr) return;

      const app = JSON.parse(dataStr);
      const rect = canvasRef.current?.getBoundingClientRect();
      if (!rect) return;

      const x = (e.clientX - rect.left - panOffset.x) / scale;
      const y = (e.clientY - rect.top - panOffset.y) / scale;

      // Type heuristic: 
      // 1. If it's the first node, it's likely a trigger.
      // 2. If it's a tool (app.id starts with 't'), it's an action.
      // 3. For apps, if we already have a trigger, it's an action.
      let nodeType = "trigger";
      if (app.id.startsWith('t')) {
        nodeType = "action";
      } else if (nodes.some(n => n.type === 'trigger')) {
        nodeType = "action";
      }

      const newNode = {
        id: `node-${Date.now()}`,
        type: nodeType,
        title: app.name,
        subtitle: "Configure action",
        position: { x, y },
        icon: app.icon,
        iconBg: app.iconBg,
        actionNumber: nodes.length + 1,
        appData: app, // Store the full app data for actions
      };

      setNodes((prev) => [...prev, newNode]);
    },
    [panOffset, scale, nodes.length, setNodes]
  );

  const handleDragOver = useCallback((e) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = "copy";
  }, []);

  const handleWheel = useCallback(
    (e) => {
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        const newScale = Math.min(Math.max(scale + delta, 0.5), 2);
        
        if (newScale !== scale) {
          const rect = canvasRef.current?.getBoundingClientRect();
          if (rect) {
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            // Calculate the mouse position relative to the content before zoom
            const contentMouseX = (mouseX - panOffset.x) / scale;
            const contentMouseY = (mouseY - panOffset.y) / scale;

            // Calculate the new pan offset to keep the content under the mouse at the same position
            const newPanX = mouseX - contentMouseX * newScale;
            const newPanY = mouseY - contentMouseY * newScale;

            setPanOffset({ x: newPanX, y: newPanY });
            setScale(newScale);
          }
        }
      }
    },
    [scale, setScale, panOffset]
  );

  const handleDeleteConnection = useCallback(
    (connectionId) => {
      setConnections((prev) => prev.filter((c) => c.id !== connectionId));
    },
    [setConnections]
  );

  const handleDeleteNode = useCallback(
    (nodeId) => {
      setNodes((prev) => prev.filter((n) => n.id !== nodeId));
      setConnections((prev) =>
        prev.filter((c) => c.from !== nodeId && c.to !== nodeId)
      );
    },
    [setNodes, setConnections]
  );

  return (
    <div
      ref={canvasRef}
      className="flex-1 bg-canvas relative overflow-hidden cursor-grab active:cursor-grabbing"
      onMouseDown={handleMouseDown}
      onMouseMove={handleMouseMove}
      onMouseUp={handleMouseUp}
      onMouseLeave={handleMouseUp}
      onDrop={handleDrop}
      onDragOver={handleDragOver}
      onWheel={handleWheel}
    >
      {/* Grid Pattern */}
      <div
        data-grid
        className="absolute inset-0 opacity-30 pointer-events-none"
        style={{
          backgroundImage: `radial-gradient(circle, var(--sidebar-border) 1px, transparent 1px)`,
          backgroundSize: `${20 * scale}px ${20 * scale}px`,
          backgroundPosition: `${panOffset.x}px ${panOffset.y}px`,
        }}
      />

      {/* Canvas Content */}
      <div
        className="absolute"
        style={{
          transform: `translate(${panOffset.x}px, ${panOffset.y}px)`,
        }}
      >
        {/* Connections */}
        <Connections
          connections={connections}
          nodes={nodes}
          scale={scale}
          onDeleteConnection={handleDeleteConnection}
          connectingFrom={connectingFrom}
          mousePos={mousePos}
          panOffset={panOffset}
        />

        {/* Nodes */}
        {nodes.map((node) => (
          <WorkflowNode
            key={node.id}
            node={node}
            scale={scale}
            onDragStart={handleNodeDragStart}
            onConnectionStart={handleConnectionStart}
            onConnectionEnd={handleConnectionEnd}
            onDelete={handleDeleteNode}
            isSelected={node.id === selectedNodeId}
            onClick={() => onNodeClick(node.id)}
          />
        ))}
      </div>
    </div>
  );
}

export default WorkflowCanvas;
