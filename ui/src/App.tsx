import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { Layout } from '@/components/layout/Layout'
import { CommandPalette } from '@/components/layout/CommandPalette'
import { useCommandPalette } from '@/hooks/useCommandPalette'
import { Projects } from '@/pages/Projects'
import { ProjectDetail } from '@/pages/ProjectDetail'
import { SkillEditor } from '@/pages/SkillEditor'
import { Library } from '@/pages/Library'
import { Search } from '@/pages/Search'
import { Settings } from '@/pages/Settings'
import { Playground } from '@/pages/Playground'
// import { Marketplace } from '@/pages/Marketplace'
import { ProjectForm } from '@/pages/ProjectForm'

function AppContent() {
  const { isOpen, close } = useCommandPalette()

  return (
    <>
      <Layout>
        <Routes>
          <Route path="/" element={<Navigate to="/projects" replace />} />
          <Route path="/projects" element={<Projects />} />
          <Route path="/projects/new" element={<ProjectForm />} />
          <Route path="/projects/:id" element={<ProjectDetail />} />
          <Route path="/projects/:id/edit" element={<ProjectForm />} />
          <Route path="/skills/new" element={<SkillEditor />} />
          <Route path="/skills/:id" element={<SkillEditor />} />
          <Route path="/library" element={<Library />} />
          {/* <Route path="/marketplace" element={<Marketplace />} /> */}
          <Route path="/playground" element={<Playground />} />
          <Route path="/search" element={<Search />} />
          <Route path="/settings" element={<Settings />} />
        </Routes>
      </Layout>
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
