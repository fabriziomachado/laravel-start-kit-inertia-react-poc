import { ChevronDown, ChevronRight, GripVertical, Plus } from 'lucide-react'
import { useState } from 'react'
import { useRegistryStore } from '../../stores/useRegistryStore'
import { useWorkflowEditorStore } from '../../stores/useWorkflowEditorStore'
import { NODE_TYPE_LABELS, NODE_TYPE_COLORS } from '../../lib/constants'
import { NODE_TYPE_ICON } from '../nodes/nodeStyles'
import type { NodeType, RegistryNode } from '../../api/types'

const TYPE_ORDER: NodeType[] = ['trigger', 'action', 'condition', 'transformer', 'control', 'utility', 'code', 'annotation']

export function NodePalette() {
  const nodes = useRegistryStore((s) => s.nodes)

  const grouped: Partial<Record<NodeType, RegistryNode[]>> = {}
  for (const node of nodes) {
    if (!grouped[node.type]) grouped[node.type] = []
    grouped[node.type]!.push(node)
  }

  return (
    <div className="space-y-1">
      {TYPE_ORDER.map((type) => {
        const nodes = grouped[type]
        if (!nodes || nodes.length === 0) return null
        return <PaletteCategory key={type} type={type} nodes={nodes} />
      })}
    </div>
  )
}

function PaletteCategory({ type, nodes }: { type: NodeType; nodes: RegistryNode[] }) {
  const [open, setOpen] = useState(true)
  const colors = NODE_TYPE_COLORS[type]
  const Icon = NODE_TYPE_ICON[type]

  return (
    <div>
      <button
        onClick={() => setOpen(!open)}
        className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
      >
        {open ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
        <Icon size={12} className={colors.text} />
        {NODE_TYPE_LABELS[type]}
        <span className="ml-auto text-[10px] text-gray-400 dark:text-gray-500">{nodes.length}</span>
      </button>
      {open && (
        <div className="ml-2 space-y-0.5">
          {nodes.map((node) => (
            <PaletteItem key={node.key} node={node} />
          ))}
        </div>
      )}
    </div>
  )
}

function PaletteItem({ node }: { node: RegistryNode }) {
  const colors = NODE_TYPE_COLORS[node.type]
  const addNode = useWorkflowEditorStore((s) => s.addNode)
  const rfNodes = useWorkflowEditorStore((s) => s.rfNodes)

  const onDragStart = (e: React.DragEvent) => {
    e.dataTransfer.setData('application/workflow-node-key', node.key)
    e.dataTransfer.effectAllowed = 'move'
  }

  const handleClick = () => {
    // Place new node offset from the last node, or at a default position
    const lastNode = rfNodes[rfNodes.length - 1]
    const position = lastNode
      ? { x: lastNode.position.x + 50, y: lastNode.position.y + 100 }
      : { x: 250, y: 150 }
    addNode(node.key, position, node)
  }

  return (
    <div
      draggable
      onDragStart={onDragStart}
      className={`flex cursor-grab items-center gap-2 rounded-md border border-transparent px-2 py-1.5 text-xs transition hover:border-gray-200 hover:bg-gray-50 dark:hover:border-gray-700 dark:hover:bg-gray-700 active:cursor-grabbing ${colors.text}`}
    >
      <GripVertical size={10} className="text-gray-300 dark:text-gray-600" />
      <span className="flex-1 text-gray-700 dark:text-gray-300">{node.label}</span>
      <button
        onClick={handleClick}
        className="rounded p-0.5 text-gray-300 hover:bg-gray-200 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
        title="Add to canvas"
      >
        <Plus size={12} />
      </button>
    </div>
  )
}
