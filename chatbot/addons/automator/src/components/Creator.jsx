"use client";

import React, { useState, useCallback, useEffect } from "react";
import Sidebar from "./Sidebar";
import Header from "./Header";
import { WorkflowCanvas } from "./WorkflowCanvas";
import NodeConfigModal from "./NodeConfigModal";
import LogsModal from "./LogsModal";

const Creator = ({ workflow, onBack }) => {
  const [scale, setScale] = useState(1);
  const [nodes, setNodes] = useState([]);
  const [connections, setConnections] = useState([]);
  const [workflowId, setWorkflowId] = useState(workflow ? workflow.id : null);
  const [workflowName, setWorkflowName] = useState(workflow ? workflow.name : 'New Workflow');
  const [selectedNodeId, setSelectedNodeId] = useState(null);
  const [showLogs, setShowLogs] = useState(false);

  // Load existing workflow data
  useEffect(() => {
    if (workflow && workflow.workflow_data) {
      setNodes(workflow.workflow_data.nodes || []);
      setConnections(workflow.workflow_data.connections || []);
    }
  }, [workflow]);

  const handleZoomIn = () => {
    setScale((prev) => Math.min(prev + 0.1, 2));
  };

  const handleZoomOut = () => {
    setScale((prev) => Math.max(prev - 0.1, 0.5));
  };

  const handleAddRouter = useCallback(() => {
    const newRouter = {
      id: `router-${Date.now()}`,
      type: "router",
      title: "Router",
      subtitle: "Branch workflow",
      position: { x: 300, y: 200 },
      icon: "🔀",
      iconBg: "bg-slate-500",
      actionNumber: nodes.length + 1,
    };
    setNodes((prev) => [...prev, newRouter]);
  }, [nodes.length]);

  const handleAddNode = useCallback((app) => {
    // Determine node type based on existing nodes and app metadata
    let nodeType = "action";
    if (app.isTrigger) {
      nodeType = "trigger";
    } else if (app.id.startsWith('t')) {
      nodeType = "action";
    } else if (!nodes.some(n => n.type === 'trigger')) {
      // If no trigger exists yet and this isn't explicitly a tool, 
      // check if it's the very first node.
      nodeType = "trigger";
    }

    const newNode = {
      id: `node-${Date.now()}`,
      type: nodeType,
      title: app.name,
      subtitle: "Configure action",
      position: { x: 300, y: 200 }, // Default position when clicking from sidebar
      icon: app.icon,
      iconBg: app.iconBg,
      actionNumber: nodes.length + 1,
      appData: app,
    };

    setNodes((prev) => [...prev, newNode]);
  }, [nodes]);

  const onSave = useCallback(async () => {
    const flowData = {
      nodes,
      connections
    };

    const method = workflowId ? 'PUT' : 'POST';
    const url = workflowId 
      ? `${window.wpbotAutomator.apiUrl}/workflows/${workflowId}`
      : `${window.wpbotAutomator.apiUrl}/workflows`;

    try {
      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpbotAutomator.nonce,
        },
        body: JSON.stringify({
          name: workflowName,
          description: 'Created via builder',
          workflow_data: flowData,
          status: 'active'
        }),
      });

      const result = await response.json();
      if (result.success) {
        if (!workflowId && result.id) {
          setWorkflowId(result.id);
        }
        alert('Workflow saved successfully!');
      } else {
        alert('Failed to save workflow.');
      }
    } catch (error) {
      console.error('Error saving workflow:', error);
      alert('An error occurred while saving.');
    }
  }, [nodes, connections, workflowId, workflowName]);

  return (
    <div className="h-screen flex flex-col overflow-hidden bg-background text-foreground">
      <Header 
        workflowName={workflowName} 
        onNameChange={setWorkflowName}
        onBack={onBack} 
        onSave={onSave} 
        onToggleLogs={() => setShowLogs(true)}
      />
      <div className="flex flex-1 overflow-hidden">
        <Sidebar
          onZoomIn={handleZoomIn}
          onZoomOut={handleZoomOut}
          onAddRouter={handleAddRouter}
          onAddNode={handleAddNode}
        />
        <WorkflowCanvas
          scale={scale}
          setScale={setScale}
          nodes={nodes}
          setNodes={setNodes}
          connections={connections}
          setConnections={setConnections}
          selectedNodeId={selectedNodeId}
          onNodeClick={setSelectedNodeId}
        />
        <NodeConfigModal
          isOpen={!!selectedNodeId}
          node={nodes.find(n => n.id === selectedNodeId)}
          onClose={() => setSelectedNodeId(null)}
          onUpdateNode={(updatedNode) => {
            setNodes(prev => prev.map(n => n.id === updatedNode.id ? updatedNode : n));
          }}
        />
        <LogsModal
          isOpen={showLogs}
          onClose={() => setShowLogs(false)}
          workflowId={workflowId}
        />
      </div>
    </div>
  );
};

export default Creator;
