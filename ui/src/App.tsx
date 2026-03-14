import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { Layout } from '@/components/layout/Layout'
import { CommandPalette } from '@/components/layout/CommandPalette'
import { useCommandPalette } from '@/hooks/useCommandPalette'
import { AuthGuard } from '@/components/auth/AuthGuard'
import { Projects } from '@/pages/Projects'
import { ProjectDetail } from '@/pages/ProjectDetail'
import { SkillEditor } from '@/pages/SkillEditor'
import { Library } from '@/pages/Library'
import { Search } from '@/pages/Search'
import { Settings } from '@/pages/Settings'
import { Playground } from '@/pages/Playground'
// import { Marketplace } from '@/pages/Marketplace'
import { ProjectForm } from '@/pages/ProjectForm'
import { ProjectSettings } from '@/pages/ProjectSettings'
import { Billing } from '@/pages/Billing'
import { ProjectVisualize } from '@/pages/ProjectVisualize'
import { Agents } from '@/pages/Agents'
import { AgentBuilder } from '@/pages/AgentBuilder'
import { Workflows } from '@/pages/Workflows'
import { WorkflowBuilder } from '@/pages/WorkflowBuilder'
import { ExecutionPlayground } from '@/pages/ExecutionPlayground'
import { ExecutionDashboard } from '@/pages/ExecutionDashboard'
import { Login } from '@/pages/Login'
import { Register } from '@/pages/Register'
import { Landing } from '@/pages/Landing'
import { WorkspaceSettings } from '@/pages/WorkspaceSettings'

function AppContent() {
  const { isOpen, close } = useCommandPalette()

  return (
    <>
      <Routes>
        {/* Public routes (no layout, no guard) */}
        <Route path="/" element={<Landing />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />

        {/* Full-screen routes (no sidebar layout) */}
        <Route
          path="/projects/:id/visualize"
          element={
            <AuthGuard>
              <ProjectVisualize />
            </AuthGuard>
          }
        />
        <Route
          path="/projects/:id/workflows/:workflowId"
          element={
            <AuthGuard>
              <WorkflowBuilder />
            </AuthGuard>
          }
        />

        {/* Protected app routes */}
        <Route
          path="/*"
          element={
            <AuthGuard>
              <Layout>
                <Routes>
                  <Route index element={<Navigate to="/projects" replace />} />
                  <Route path="/projects" element={<Projects />} />
                  <Route path="/projects/new" element={<ProjectForm />} />
                  <Route path="/projects/:id" element={<ProjectDetail />} />
                  <Route path="/projects/:id/settings" element={<ProjectSettings />} />
                  <Route path="/skills/new" element={<SkillEditor />} />
                  <Route path="/skills/:id" element={<SkillEditor />} />
                  <Route path="/projects/:id/workflows" element={<Workflows />} />
                  <Route path="/agents" element={<Agents />} />
                  <Route path="/agents/new" element={<AgentBuilder />} />
                  <Route path="/agents/:id" element={<AgentBuilder />} />
                  <Route path="/library" element={<Library />} />
                  {/* <Route path="/marketplace" element={<Marketplace />} /> */}
                  <Route path="/playground" element={<Playground />} />
                  <Route path="/projects/:id/execute" element={<ExecutionPlayground />} />
                  <Route path="/projects/:id/runs" element={<ExecutionDashboard />} />
                  <Route path="/search" element={<Search />} />
                  <Route path="/workspace" element={<WorkspaceSettings />} />
                  <Route path="/settings" element={<Settings />} />
                  <Route path="/billing" element={<Billing />} />
                </Routes>
              </Layout>
            </AuthGuard>
          }
        />
      </Routes>
      <CommandPalette isOpen={isOpen} onClose={close} />
    </>
  )
}

function App() {
  return (
    <BrowserRouter>
      <AppContent />
    </BrowserRouter>
  )
}

export default App
