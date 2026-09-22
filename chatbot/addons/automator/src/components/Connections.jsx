"use client";

import React from "react";

export function Connections({
  connections,
  nodes,
  scale,
  onDeleteConnection,
  connectingFrom,
  mousePos,
  panOffset,
}) {
  const getNodePosition = (nodeId, handle = "right") => {
    const node = nodes.find((n) => n.id === nodeId);
    if (!node) return { x: 0, y: 0 };

    const nodeWidth = 180;
    const nodeHeight = 56;
    const labelHeight = 24;

    let x = node.position.x * scale;
    let y = (node.position.y + labelHeight) * scale;

    switch (handle) {
      case "left":
        y += (nodeHeight * scale) / 2;
        break;
      case "right":
        x += nodeWidth * scale;
        y += (nodeHeight * scale) / 2;
        break;
      default:
        x += nodeWidth * scale;
        y += (nodeHeight * scale) / 2;
    }

    return { x, y };
  };

  const createBezierPath = (from, to) => {
    const dx = Math.abs(to.x - from.x);
    const controlOffset = Math.min(dx * 0.5, 100);

    return `M ${from.x} ${from.y} C ${from.x + controlOffset} ${from.y}, ${to.x - controlOffset} ${to.y}, ${to.x} ${to.y}`;
  };

  const getConnectingLineEnd = () => {
    if (!connectingFrom) return null;

    const fromPos = getNodePosition(
      connectingFrom.nodeId,
      connectingFrom.handle
    );

    return {
      from: fromPos,
      to: {
        x: mousePos.x - panOffset.x,
        y: mousePos.y - panOffset.y,
      },
    };
  };

  const connectingLine = getConnectingLineEnd();

  return (
    <svg
      className="absolute inset-0 pointer-events-none overflow-visible"
      style={{ width: "100%", height: "100%", minWidth: "2000px", minHeight: "2000px" }}
    >
      <defs>
        <marker
          id="arrowhead"
          markerWidth="10"
          markerHeight="10"
          refX="8"
          refY="5"
          orient="auto"
        >
          <path
            d="M 0 0 L 10 5 L 0 10 z"
            fill="currentColor"
            className="text-foreground/50"
          />
        </marker>
        <marker
          id="arrowhead-active"
          markerWidth="10"
          markerHeight="10"
          refX="8"
          refY="5"
          orient="auto"
        >
          <path d="M 0 0 L 10 5 L 0 10 z" className="fill-primary" />
        </marker>
      </defs>

      {/* Existing connections */}
      {connections.map((conn) => {
        const fromPos = getNodePosition(conn.from, conn.fromHandle || "right");
        const toPos = getNodePosition(conn.to, conn.toHandle || "left");
        const path = createBezierPath(fromPos, toPos);

        return (
          <g key={conn.id} className="group/connection">
            {/* Invisible wider path for easier clicking */}
            <path
              d={path}
              fill="none"
              stroke="transparent"
              strokeWidth={20}
              className="pointer-events-auto cursor-pointer"
              onClick={() => onDeleteConnection(conn.id)}
            />
            {/* Visible path */}
            <path
              d={path}
              fill="none"
              stroke="currentColor"
              strokeWidth={2}
              className="text-foreground/40 pointer-events-none"
              markerEnd="url(#arrowhead)"
            />
            {/* Hover state path */}
            <path
              d={path}
              fill="none"
              stroke="currentColor"
              strokeWidth={3}
              className="text-destructive opacity-0 group-hover/connection:opacity-100 transition-opacity pointer-events-none"
              markerEnd="url(#arrowhead)"
            />
          </g>
        );
      })}

      {/* Active connecting line */}
      {connectingLine && (
        <path
          d={createBezierPath(connectingLine.from, connectingLine.to)}
          fill="none"
          stroke="currentColor"
          strokeWidth={2}
          strokeDasharray="5,5"
          className="text-primary"
          markerEnd="url(#arrowhead-active)"
        />
      )}
    </svg>
  );
}

export default Connections;
