import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { WorkflowListPage } from './components/workflow-list/WorkflowListPage'
import { WorkflowEditorPage } from './components/editor/WorkflowEditorPage'

export default function App() {
  return (
    <BrowserRouter basename="/workflow-editor">
      <Routes>
        <Route path="/" element={<WorkflowListPage />} />
        <Route path="/:id" element={<WorkflowEditorPage />} />
      </Routes>
    </BrowserRouter>
  )
}
