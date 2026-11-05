import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Button } from './Button'
import { Card } from './Card'
import { DataTable } from './DataTable'
import { Input } from './Input'

describe('shared UI primitives', () => {
  it('renders accessible button, input, card, and data table primitives', () => {
    render(
      <>
        <Button type="button">Save changes</Button>
        <Input aria-label="Department code" name="departmentCode" />
        <Card aria-label="Reference card"><p>Reference details</p></Card>
        <DataTable aria-label="Departments"><thead><tr><th scope="col">Department</th></tr></thead><tbody><tr><td>Finance</td></tr></tbody></DataTable>
      </>,
    )

    expect(screen.getByRole('button', { name: 'Save changes' })).toBeEnabled()
    expect(screen.getByRole('textbox', { name: 'Department code' })).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Reference card' })).toHaveTextContent('Reference details')
    expect(screen.getByRole('table', { name: 'Departments' })).toHaveTextContent('Finance')
  })
})
